<?php

declare(strict_types=1);

namespace VediSMM\WordPress\WordPress;

use Throwable;
use VediSMM\WordPress\Api\VediSMMGateway;
use VediSMM\WordPress\Application\SubmissionService;

final class PostSubmissionHandler
{
    public function __construct(private readonly ?SubmissionService $submissionService = null)
    {
    }

    public function register(): void
    {
        if (function_exists('add_action')) {
            add_action('save_post', [$this, 'handle'], 10, 3);
        }
    }

    public function handle(int $postId, mixed $post, mixed $update): void
    {
        if (!$this->isExplicitSubmission($postId, $post)) {
            return;
        }

        $tracking = self::normalizeTracking(is_array($_POST['vedismm_tracking'] ?? null)
            ? $_POST['vedismm_tracking']
            : []);
        if (function_exists('update_post_meta')) {
            update_post_meta($postId, '_vedismm_tracking', $tracking);
        }

        $service = $this->submissionService ?? $this->createSubmissionService();
        if (!$service instanceof SubmissionService) {
            return;
        }

        $targets = is_array($_POST['vedismm_targets'] ?? null) ? $_POST['vedismm_targets'] : [];
        $revision = self::revision($post);

        try {
            $service->submit(
                [
                    'ID' => $postId,
                    'post_title' => is_object($post) ? (string) ($post->post_title ?? '') : '',
                    'post_content' => is_object($post) ? (string) ($post->post_content ?? '') : '',
                    'permalink' => function_exists('get_permalink') ? (string) get_permalink($postId) : null,
                ],
                [
                    'account_ids' => is_array($targets['account_ids'] ?? null) ? $targets['account_ids'] : [],
                    'group_ids' => is_array($targets['group_ids'] ?? null) ? $targets['group_ids'] : [],
                    'media_ids' => is_array($targets['media_ids'] ?? null) ? $targets['media_ids'] : [],
                ],
                [
                    'can_publish' => true,
                    'nonce_valid' => true,
                    'revision' => $revision,
                    'action' => 'draft',
                    'tracking' => $tracking,
                ],
            );
        } catch (Throwable $exception) {
            if (function_exists('error_log')) {
                error_log('VediSMM submission failed: ' . $exception->getMessage());
            }
        }
    }

    /** @return array{shorten_links: bool, add_source: bool} */
    public static function normalizeTracking(array $tracking): array
    {
        $shortenLinks = self::checked($tracking['shorten_links'] ?? false);

        return [
            'shorten_links' => $shortenLinks,
            'add_source' => $shortenLinks && self::checked($tracking['add_source'] ?? false),
        ];
    }

    private function isExplicitSubmission(int $postId, mixed $post): bool
    {
        if (!is_object($post) || !in_array((string) ($post->post_type ?? ''), ['post', 'page'], true)) {
            return false;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }
        if ((function_exists('wp_is_post_autosave') && wp_is_post_autosave($postId))
            || (function_exists('wp_is_post_revision') && wp_is_post_revision($postId))) {
            return false;
        }
        if (($_POST['vedismm_submit_action'] ?? null) !== 'draft') {
            return false;
        }
        if (!function_exists('wp_verify_nonce')
            || wp_verify_nonce($_POST['vedismm_nonce'] ?? '', MetaBox::NONCE_ACTION) === false) {
            return false;
        }

        return function_exists('current_user_can') && current_user_can('edit_post', $postId);
    }

    private function createSubmissionService(): ?SubmissionService
    {
        if (!function_exists('get_option')) {
            return null;
        }

        $token = trim((string) get_option('vedismm_token', ''));
        if ($token === '') {
            return null;
        }

        $installationId = trim((string) get_option('vedismm_installation_id', ''));
        if ($installationId === '') {
            $installationId = substr(hash('sha256', (string) get_option('siteurl', 'wordpress')), 0, 24);
        }
        $configuredBaseUrl = getenv('VEDISMM_API_BASE_URL');
        $baseUrl = is_string($configuredBaseUrl) && trim($configuredBaseUrl) !== ''
            ? trim($configuredBaseUrl)
            : 'https://vedismm.ru/api/v1';

        return new SubmissionService(
            new VediSMMGateway($token, new WordPressTransport(), $baseUrl),
            $installationId,
        );
    }

    private static function checked(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }

    private static function revision(mixed $post): int
    {
        $modified = is_object($post) ? trim((string) ($post->post_modified_gmt ?? '')) : '';
        $timestamp = $modified === '' ? false : strtotime($modified . ' UTC');

        return $timestamp === false ? 1 : max(1, $timestamp);
    }
}
