<?php

declare(strict_types=1);

namespace App\Admin;

use App\Http\Api\ApiKeyStore;
use App\Http\Api\Authenticator;

/**
 * Admin UI for /api/v1 key management.
 *
 * Lives under ADMIN_MODE=full only -- the sidebar nav hides it on
 * minimal-mode sites because the public REST API is an advanced
 * feature most single-operator deployments don't need. Routes
 * resolve regardless of mode, so an operator who knows the URL can
 * still reach them on a minimal-mode install.
 *
 * Flow:
 *   GET  /admin/api-keys           list active + revoked keys
 *   GET  /admin/api-keys/new       form (label + scope)
 *   POST /admin/api-keys/create    generate key, persist hash,
 *                                  flash plaintext ONCE
 *   POST /admin/api-keys/{hash}/revoke   mark revoked_at
 *
 * The plaintext is shown to the operator exactly once -- after that,
 * only the SHA-256 hash exists anywhere on disk, so a leak of
 * storage/api-keys.json cannot grant API access.
 */
final class ApiKeysController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $store = new ApiKeyStore(ApiKeyStore::defaultPath());
        $keys  = $store->all();

        // Surface the freshly-created plaintext if the previous request
        // was POST /create; the session flash carries it across the
        // redirect. Cleared after rendering so a refresh doesn't keep
        // showing it.
        $flash = $this->getFlash();
        $newKey = null;
        $messages = [];
        foreach ($flash as $f) {
            if (($f['type'] ?? '') === 'api_key_plaintext') {
                $newKey = (string) ($f['message'] ?? '');
            } else {
                $messages[] = $f;
            }
        }

        $this->render('api-keys/index', [
            'pageTitle' => 'API Keys',
            'keys'      => $this->presentKeys($keys),
            'newKey'    => $newKey,
            'csrf'      => $this->generateCsrf(),
            'flash'     => $messages,
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();

        $this->render('api-keys/create', [
            'pageTitle' => 'New API Key',
            'csrf'      => $this->generateCsrf(),
            'flash'     => $this->getFlash(),
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->flash('error', 'Invalid security token.');
            $this->redirect('/admin/api-keys/new');
            return;
        }

        $label = trim((string) $this->post('label', ''));
        $scope = trim((string) $this->post('scope', 'read'));

        try {
            $generated = Authenticator::generateKey();
            $store = new ApiKeyStore(ApiKeyStore::defaultPath());
            $store->create($generated['hash'], $label, $scope);
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect('/admin/api-keys/new');
            return;
        } catch (\Throwable $e) {
            $this->flash('error', 'Failed to create key: ' . $e->getMessage());
            $this->redirect('/admin/api-keys/new');
            return;
        }

        // Plaintext goes into a one-shot flash channel. The list view
        // pulls it out, displays it once, and the next request never
        // sees it again -- nothing else on disk knows the plaintext.
        $this->flash('api_key_plaintext', $generated['plaintext']);
        $this->flash('success', 'Key "' . $label . '" created. Copy it now -- it will not be shown again.');
        $this->redirect('/admin/api-keys');
    }

    public function revoke(string $hash): void
    {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->flash('error', 'Invalid security token.');
            $this->redirect('/admin/api-keys');
            return;
        }

        // Hash arrives in the URL as 64-hex; reject anything else
        // before touching the store so a malformed parameter doesn't
        // hit the flock layer.
        if (preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
            $this->flash('error', 'Invalid key identifier.');
            $this->redirect('/admin/api-keys');
            return;
        }

        $store = new ApiKeyStore(ApiKeyStore::defaultPath());
        if ($store->revoke($hash)) {
            $this->flash('success', 'Key revoked.');
        } else {
            $this->flash('warning', 'Key was already revoked or does not exist.');
        }
        $this->redirect('/admin/api-keys');
    }

    /**
     * @param list<array<string, mixed>> $keys
     * @return list<array<string, mixed>>
     */
    private function presentKeys(array $keys): array
    {
        // Newest first; revoked keys stay in the list (audit trail).
        usort($keys, static function ($a, $b): int {
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });
        return array_map(static function (array $row): array {
            $hash = (string) ($row['key_hash'] ?? '');
            return [
                'hash'         => $hash,
                'hash_short'   => $hash === '' ? '' : substr($hash, 0, 8) . '...' . substr($hash, -4),
                'label'        => (string) ($row['label'] ?? ''),
                'scope'        => (string) ($row['scope'] ?? ''),
                'created_at'   => (string) ($row['created_at'] ?? ''),
                'last_used_at' => $row['last_used_at'] ?? null,
                'revoked_at'   => $row['revoked_at'] ?? null,
                'is_active'    => ($row['revoked_at'] ?? null) === null,
            ];
        }, array_values($keys));
    }
}
