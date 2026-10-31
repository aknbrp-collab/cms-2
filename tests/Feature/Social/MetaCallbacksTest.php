<?php

use App\Enums\SiteKey;
use App\Enums\SocialProvider;
use App\Models\Site;
use App\Models\SocialAccount;

beforeEach(function () {
    seedCms();
    config(['social.meta.app_secret' => 'meta-secret']);
    $this->account = SocialAccount::query()->create([
        'site_id' => Site::findByKey(SiteKey::Pondok)->id, 'provider' => SocialProvider::Facebook,
        'external_id' => 'page-1', 'provider_user_id' => 'fb-user-9', 'name' => 'Page', 'access_token' => 't',
    ]);
});

function signedRequest(array $payload, string $secret = 'meta-secret'): string
{
    $encode = fn (string $value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    $payloadPart = $encode(json_encode(['algorithm' => 'HMAC-SHA256', ...$payload]));

    return $encode(hash_hmac('sha256', $payloadPart, $secret, true)).'.'.$payloadPart;
}

test('the data deletion callback deletes the user\'s accounts and returns a status url', function () {
    $response = $this->post(route('social.meta.data-deletion'), ['signed_request' => signedRequest(['user_id' => 'fb-user-9'])])
        ->assertOk()
        ->assertJsonStructure(['url', 'confirmation_code']);

    expect(SocialAccount::query()->withoutGlobalScopes()->count())->toBe(0);
    $this->get($response->json('url'))->assertOk()->assertSee($response->json('confirmation_code'));
});

test('forged signed requests are rejected', function () {
    $this->post(route('social.meta.data-deletion'), ['signed_request' => signedRequest(['user_id' => 'fb-user-9'], 'wrong')])->assertStatus(400);

    expect(SocialAccount::query()->withoutGlobalScopes()->count())->toBe(1);
});

test('deauthorize marks the accounts as revoked', function () {
    $this->post(route('social.meta.deauthorize'), ['signed_request' => signedRequest(['user_id' => 'fb-user-9'])])->assertOk();

    expect($this->account->fresh()->status)->toBe(SocialAccount::REVOKED)->and($this->account->fresh()->isUsable())->toBeFalse();
});

test('legal pages are public', function () {
    $this->get(route('legal.privacy'))->assertOk()->assertSee('Kebijakan Privasi')->assertSee('English summary');
    $this->get(route('legal.terms'))->assertOk()->assertSee('Music Usage Confirmation');
});
