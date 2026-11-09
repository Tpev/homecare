<?php

use App\Models\Lead;
use App\Services\Crm\LeadDeduplicator;
use App\Support\LeadPhone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A phone match must never silently detach a different household's account.
        // Check before changing the schema, including on databases with implicit DDL commits.
        $accounts = [];
        DB::table('leads')->whereNotNull('family_account_id')->orderBy('id')->each(function ($lead) use (&$accounts) {
            if ($phone = LeadPhone::key($lead->phone)) {
                $key = $lead->lead_type.':'.$phone;
                if (isset($accounts[$key]) && $accounts[$key]['account'] !== $lead->family_account_id) {
                    throw new RuntimeException('Resolve different family accounts on duplicate leads '
                        .$accounts[$key]['lead'].' and '.$lead->id.' before phone deduplication.');
                }
                $accounts[$key] = ['account' => $lead->family_account_id, 'lead' => $lead->id];
            }
        });

        if (! Schema::hasColumn('leads', 'phone_key')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->string('phone_key', 20)->nullable();
                $table->unique(['lead_type', 'phone_key']);
            });
        }
        if (! Schema::hasTable('lead_external_identities')) {
            Schema::create('lead_external_identities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
                $table->string('external_source', 80);
                $table->string('external_id', 160);
                $table->unique(['external_source', 'external_id'], 'lead_external_identity_unique');
            });
        }

        Lead::withoutEvents(function () {
            Lead::query()->eachById(function (Lead $lead) {
                DB::transaction(function () use ($lead) {
                    $lead = Lead::query()->lockForUpdate()->find($lead->id);
                    if (! $lead) {
                        return;
                    }
                    $match = Lead::query()->where('lead_type', $lead->lead_type)
                        ->matchingPhone($lead->phone)->whereKeyNot($lead->id)->lockForUpdate()->first();
                    if ($match) {
                        app(LeadDeduplicator::class)->merge($match, $lead);
                    } else {
                        $lead->save();
                    }
                });
            });
        });
    }

    public function down(): void
    {
        // Merge snapshots remain in lead_activities; rolling back schema cannot split histories.
        Schema::dropIfExists('lead_external_identities');
        Schema::table('leads', function (Blueprint $table) {
            $table->dropUnique(['lead_type', 'phone_key']);
            $table->dropColumn('phone_key');
        });
    }
};
