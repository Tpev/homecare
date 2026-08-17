<?php

namespace App\Services\AiSupport;

use App\Models\AiSupportPilotGrant;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\AiSupport\AiSupportEligibilityResult;
use Throwable;

class AiSupportEligibilityService
{
    public function __construct(private readonly AiSupportControlService $controls) {}

    public function evaluate(
        User $user,
        string $capabilityId = 'support_answers_v1',
        ?SupportTicket $conversation = null,
        ?string $toolId = null,
    ): AiSupportEligibilityResult {
        if (! in_array($user->role, (array) config('ai_support.supported_roles', []), true)) {
            return AiSupportEligibilityResult::deny('unsupported_role');
        }

        if (! config('ai_support.runtime_available', false)) {
            return AiSupportEligibilityResult::deny('runtime_deployment_guard_off');
        }

        try {
            $evidence = [];
            foreach (['master_enabled', 'user_visible_enabled'] as $requiredControl) {
                $state = $this->controls->state($requiredControl);
                $evidence[$requiredControl.'_version'] = $state['version_id'];
                if (! $state['enabled']) {
                    return AiSupportEligibilityResult::deny($requiredControl.'_off', $evidence);
                }
            }

            $humanOnly = $this->controls->state('human_only');
            $evidence['human_only_version'] = $humanOnly['version_id'];
            if ($humanOnly['enabled']) {
                return AiSupportEligibilityResult::deny('human_only_mode', $evidence);
            }

            $generalRelease = $this->controls->state('general_release_enabled');
            $evidence['general_release_version'] = $generalRelease['version_id'];
            $evidence['availability_mode'] = $generalRelease['enabled'] ? 'everyone' : 'pilot';

            $roleControl = $this->controls->state('role.'.$user->role);
            $evidence['role_control_version'] = $roleControl['version_id'];
            if (! $roleControl['enabled']) {
                return AiSupportEligibilityResult::deny('role_not_released', $evidence);
            }

            $grant = null;
            if ($generalRelease['enabled']) {
                $bundle = collect((array) config('ai_support.bundles', []))
                    ->first(fn (array $candidate): bool => in_array($user->role, (array) ($candidate['roles'] ?? []), true));
                if (! $bundle || ! in_array($capabilityId, (array) ($bundle['capabilities'] ?? []), true)) {
                    return AiSupportEligibilityResult::deny('capability_not_available_for_role', $evidence);
                }
            } else {
                $grant = AiSupportPilotGrant::query()
                    ->where('user_id', $user->id)
                    ->effectiveAt()
                    ->latest('starts_at')
                    ->get()
                    ->first(fn (AiSupportPilotGrant $candidate): bool => $candidate->includesCapability($capabilityId));

                if (! $grant) {
                    return AiSupportEligibilityResult::deny('no_active_exact_user_grant', $evidence);
                }
            }

            $capabilityControl = $this->controls->state('capability.'.$capabilityId);
            $evidence['capability_control_version'] = $capabilityControl['version_id'];
            if (! $capabilityControl['enabled']) {
                return AiSupportEligibilityResult::deny('capability_not_released', $evidence);
            }

            if ($toolId !== null) {
                $toolControlKey = 'tool.'.$toolId;
                if (! in_array($toolControlKey, $this->controls->keys(), true)) {
                    return AiSupportEligibilityResult::deny('tool_not_registered', $evidence);
                }

                $toolControl = $this->controls->state($toolControlKey);
                $evidence['tool_control_version'] = $toolControl['version_id'];
                if (! $toolControl['enabled']) {
                    return AiSupportEligibilityResult::deny('tool_not_released', $evidence);
                }
            }

            if ($conversation && (($conversation->responder_mode ?? 'human_only') !== 'automated')) {
                return AiSupportEligibilityResult::deny('conversation_human_only', $evidence);
            }

            $evidence['policy_version'] = (string) config('ai_support.policy_version');

            return AiSupportEligibilityResult::allow($grant?->id, $evidence);
        } catch (Throwable) {
            return AiSupportEligibilityResult::deny('eligibility_store_unavailable');
        }
    }
}
