<?php

namespace App\Http\Controllers;

use App\Exceptions\ContentMcpOAuthException;
use App\Models\ContentMcpOAuthClient;
use App\Services\Content\ContentMcpOAuth;
use App\Services\Content\ContentMcpScopes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class ContentMcpOAuthAuthorizationController extends Controller
{
    public function show(Request $request, ContentMcpOAuth $oauth, ContentMcpScopes $scopes): View|RedirectResponse
    {
        $parameters = $this->validatedParameters($request, $oauth);

        try {
            $granted = $scopes->authorize($request->user(), $scopes->parse($parameters['scope']));
        } catch (ContentMcpOAuthException $exception) {
            return $this->redirectError($parameters['redirect_uri'], $parameters['state'], $exception);
        }

        return view('content-mcp.oauth-consent', [
            'client' => $parameters['client'],
            'parameters' => $parameters,
            'scopeDescriptions' => collect($granted)->mapWithKeys(
                fn (string $scope): array => [$scope => (string) config('content_mcp.scopes.'.$scope)]
            ),
        ]);
    }

    public function decide(Request $request, ContentMcpOAuth $oauth, ContentMcpScopes $scopes): RedirectResponse
    {
        $parameters = $this->validatedParameters($request, $oauth);
        if ($request->input('decision') !== 'allow') {
            return $this->redirectError(
                $parameters['redirect_uri'],
                $parameters['state'],
                new ContentMcpOAuthException('access_denied', 'The user denied the authorization request.'),
            );
        }

        try {
            $granted = $scopes->authorize($request->user(), $scopes->parse($parameters['scope']));
            $code = $oauth->issueAuthorizationCode(
                $parameters['client'],
                $request->user(),
                $parameters['redirect_uri'],
                $granted,
                $parameters['resource'],
                $parameters['code_challenge'],
            );
        } catch (ContentMcpOAuthException $exception) {
            return $this->redirectError($parameters['redirect_uri'], $parameters['state'], $exception);
        }

        return redirect()->away($this->appendQuery($parameters['redirect_uri'], [
            'code' => $code,
            'state' => $parameters['state'],
        ]));
    }

    /** @return array{client:ContentMcpOAuthClient,client_id:string,redirect_uri:string,response_type:string,scope:string,state:string,code_challenge:string,code_challenge_method:string,resource:string} */
    private function validatedParameters(Request $request, ContentMcpOAuth $oauth): array
    {
        $validator = Validator::make($request->all(), [
            'client_id' => ['required', 'string', 'max:100'],
            'redirect_uri' => ['required', 'string', 'max:2048'],
            'response_type' => ['required', 'in:code'],
            'scope' => ['nullable', 'string', 'max:1000'],
            'state' => ['required', 'string', 'max:2048'],
            'code_challenge' => ['required', 'regex:/^[A-Za-z0-9_-]{43}$/D'],
            'code_challenge_method' => ['required', 'in:S256'],
            'resource' => ['required', 'string', 'max:255'],
        ]);

        abort_if($validator->fails(), 400, 'The OAuth authorization request is malformed.');
        $data = $validator->validated();

        try {
            $client = $oauth->client($data['client_id']);
        } catch (ContentMcpOAuthException) {
            abort(400, 'The OAuth client is unknown, expired, or revoked.');
        }
        abort_unless($client->acceptsRedirectUri($data['redirect_uri']), 400, 'The OAuth redirect URI is not registered.');

        if (! hash_equals((string) config('content_mcp.resource'), $data['resource'])) {
            abort(400, 'The requested OAuth resource is invalid.');
        }

        return [...$data, 'scope' => (string) ($data['scope'] ?? ''), 'client' => $client];
    }

    private function redirectError(string $redirectUri, string $state, ContentMcpOAuthException $exception): RedirectResponse
    {
        return redirect()->away($this->appendQuery($redirectUri, [
            'error' => $exception->oauthError,
            'error_description' => $exception->getMessage(),
            'state' => $state,
        ]));
    }

    private function appendQuery(string $uri, array $parameters): string
    {
        return $uri.(str_contains($uri, '?') ? '&' : '?').http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }
}
