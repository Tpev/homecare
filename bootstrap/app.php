<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Tiptap stores meaningful word-boundary whitespace inside nested text nodes.
        // Trimming those values turns "text [link] text" into joined words in HTML.
        $middleware->trimStrings(except: ['content_json.*.text', '*.content_json.*.text']);

        $middleware->alias([
            'admin.email' => \App\Http\Middleware\EnsureAdminEmail::class,
            'content.access' => \App\Http\Middleware\EnsureContentAccess::class,
            'crm.access' => \App\Http\Middleware\EnsureCrmAccess::class,
            'sdr.access' => \App\Http\Middleware\EnsureSdrAccess::class,
            'caregiver.role' => \App\Http\Middleware\EnsureCaregiverRole::class,
            'family.role' => \App\Http\Middleware\EnsureFamilyRole::class,
            'continuous.coverage' => \App\Http\Middleware\EnsureContinuousCoverageAccess::class,
            'voice.agent' => \App\Http\Middleware\EnsureVoiceAgentToken::class,
            'content.api' => \App\Http\Middleware\AuthenticateContentApi::class,
            'content.api.size' => \App\Http\Middleware\LimitContentApiRequestSize::class,
            'content.api.audit' => \App\Http\Middleware\AuditContentApiRequest::class,
            'content.ability' => \App\Http\Middleware\RequireContentApiAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Validation\ValidationException $exception, \Illuminate\Http\Request $request) {
            if (! $request->is('api/content/v1/*')) {
                return null;
            }

            $conflict = array_key_exists('conflict', $exception->errors());

            $postId = $request->attributes->get('content_api_blog_post_id');

            return response()->json([
                'message' => $conflict ? 'The article edit version is stale.' : 'The request could not be processed.',
                'code' => $conflict ? 'edit_conflict' : 'validation_failed',
                'errors' => $exception->errors(),
                'meta' => array_filter([
                    'request_id' => $request->attributes->get('content_api_request_id'),
                    'current_edit_version' => $conflict && $postId
                        ? \App\Models\BlogPost::query()->whereKey($postId)->value('edit_version')
                        : null,
                ], fn ($value) => $value !== null),
            ], $conflict ? 409 : 422);
        });
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $exception, \Illuminate\Http\Request $request) {
            if (! $request->is('api/content/v1/*')) {
                return null;
            }

            return response()->json([
                'message' => 'The token actor is not authorized for this resource.',
                'code' => 'forbidden',
                'errors' => ['authorization' => [$exception->getMessage() ?: 'This operation is not permitted.']],
                'meta' => array_filter(['request_id' => $request->attributes->get('content_api_request_id')]),
            ], 403);
        });
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $exception, \Illuminate\Http\Request $request) {
            if (! $request->is('api/content/v1/*')) {
                return null;
            }

            return response()->json([
                'message' => 'The requested content resource was not found.',
                'code' => 'not_found',
                'errors' => [],
                'meta' => array_filter(['request_id' => $request->attributes->get('content_api_request_id')]),
            ], 404);
        });
    })->create();
