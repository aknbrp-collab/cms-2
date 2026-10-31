<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\SocialProvider;
use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Callbacks Meta requires for app review: data deletion and deauthorization.
 * Both receive a "signed_request" signed with the app secret.
 */
class MetaCallbackController extends Controller
{
    /**
     * POST /api/social/meta/data-deletion
     */
    public function dataDeletion(Request $request): JsonResponse
    {
        $userId = $this->userId((string) $request->input('signed_request'));

        if ($userId === null) {
            return response()->json(['error' => 'Invalid signed_request.'], 400);
        }

        $deleted = $this->accounts($userId)->each(fn (SocialAccount $account) => $account->delete())->count();
        $code = Str::upper(Str::random(12));

        Cache::put("meta-deletion:{$code}", ['deleted' => $deleted, 'at' => now()->toIso8601String()], now()->addDays(90));
        Audit::record('social.data_deleted', meta: ['provider_user_id' => $userId, 'accounts' => $deleted, 'code' => $code]);

        return response()->json([
            'url' => route('social.data-deletion.status', $code),
            'confirmation_code' => $code,
        ]);
    }

    /**
     * POST /api/social/meta/deauthorize
     */
    public function deauthorize(Request $request): JsonResponse
    {
        $userId = $this->userId((string) $request->input('signed_request'));

        if ($userId === null) {
            return response()->json(['error' => 'Invalid signed_request.'], 400);
        }

        $this->accounts($userId)->each(fn (SocialAccount $account) => $account->forceFill([
            'status' => SocialAccount::REVOKED,
            'last_error' => __('Access was removed in Facebook settings.'),
        ])->save());

        return response()->json(['status' => 'ok']);
    }

    /**
     * Public page showing the result of a deletion request.
     */
    public function status(string $code): View
    {
        $result = Cache::get("meta-deletion:{$code}");
        abort_if($result === null, 404);

        return view('social.data-deletion', ['code' => $code, 'result' => $result]);
    }

    /**
     * @return Collection<int, SocialAccount>
     */
    protected function accounts(string $userId): Collection
    {
        return SocialAccount::query()->withoutGlobalScopes()
            ->whereIn('provider', [SocialProvider::Facebook->value, SocialProvider::Instagram->value])
            ->where('provider_user_id', $userId)
            ->get();
    }

    /**
     * Verify Meta's signed_request (HMAC-SHA256 with the app secret) and return the user id.
     */
    protected function userId(string $signedRequest): ?string
    {
        $secret = (string) config('social.meta.app_secret');

        if ($secret === '' || ! str_contains($signedRequest, '.')) {
            return null;
        }

        [$encodedSignature, $encodedPayload] = explode('.', $signedRequest, 2);
        $signature = $this->base64UrlDecode($encodedSignature);
        $payload = json_decode($this->base64UrlDecode($encodedPayload), true);

        if (! is_array($payload) || ($payload['algorithm'] ?? '') !== 'HMAC-SHA256') {
            return null;
        }

        if (! hash_equals(hash_hmac('sha256', $encodedPayload, $secret, true), $signature)) {
            return null;
        }

        return isset($payload['user_id']) ? (string) $payload['user_id'] : null;
    }

    protected function base64UrlDecode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'));
    }
}
