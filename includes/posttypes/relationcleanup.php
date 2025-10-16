<?php

declare(strict_types=1);

namespace EstateOffice\PostTypes;

use function add_action;
use function array_diff;
use function array_map;
use function array_unique;
use function delete_post_meta;
use function get_post_meta;
use function get_post_type;
use function update_post_meta;

defined('ABSPATH') || exit();

final class RelationCleanup
{
    public static function bootstrap(): void
    {
        add_action('before_delete_post', [self::class, 'handleDelete']);
    }

    public static function handleDelete(int $postId): void
    {
        $postType = get_post_type($postId);

        if (!$postType) {
            return;
        }

        switch ($postType) {
            case AgreementRegister::POST_TYPE:
                self::cleanupAgreementRelations($postId);
                break;
            case PropertyRegister::POST_TYPE:
                self::cleanupRelatedAgreementList(
                    $postId,
                    PropertyMeta::AGREEMENTS_META_KEY,
                    'estate_agreement_properties'
                );
                break;
            case ClientRegister::POST_TYPE:
                self::cleanupRelatedAgreementList(
                    $postId,
                    ClientMeta::AGREEMENTS_META_KEY,
                    'estate_agreement_clients'
                );
                break;
            case SearchRegister::POST_TYPE:
                self::cleanupRelatedAgreementList(
                    $postId,
                    SearchMeta::AGREEMENTS_META_KEY,
                    'estate_agreement_searches'
                );
                break;
        }
    }

    private static function cleanupAgreementRelations(int $agreementId): void
    {
        $clients    = self::normaliseIds(get_post_meta($agreementId, 'estate_agreement_clients', true));
        $properties = self::normaliseIds(get_post_meta($agreementId, 'estate_agreement_properties', true));
        $searches   = self::normaliseIds(get_post_meta($agreementId, 'estate_agreement_searches', true));

        foreach ($clients as $clientId) {
            self::removeRelationMeta($clientId, ClientRegister::POST_TYPE, ClientMeta::AGREEMENTS_META_KEY, $agreementId);
        }

        foreach ($properties as $propertyId) {
            self::removeRelationMeta($propertyId, PropertyRegister::POST_TYPE, PropertyMeta::AGREEMENTS_META_KEY, $agreementId);
        }

        foreach ($searches as $searchId) {
            self::removeRelationMeta($searchId, SearchRegister::POST_TYPE, SearchMeta::AGREEMENTS_META_KEY, $agreementId);
        }
    }

    private static function cleanupRelatedAgreementList(int $postId, string $metaKey, string $agreementMetaKey): void
    {
        $agreements = self::normaliseIds(get_post_meta($postId, $metaKey, true));

        if (empty($agreements)) {
            return;
        }

        foreach ($agreements as $agreementId) {
            self::removeAgreementReference($agreementId, $agreementMetaKey, $postId);
        }
    }

    private static function removeRelationMeta(int $postId, string $expectedType, string $metaKey, int $agreementId): void
    {
        if ($postId <= 0 || get_post_type($postId) !== $expectedType) {
            return;
        }

        $current = self::normaliseIds(get_post_meta($postId, $metaKey, true));

        if (empty($current)) {
            return;
        }

        $updated = array_values(array_diff($current, [$agreementId]));

        if (empty($updated)) {
            delete_post_meta($postId, $metaKey);

            return;
        }

        update_post_meta($postId, $metaKey, $updated);
    }

    private static function removeAgreementReference(int $agreementId, string $metaKey, int $relatedId): void
    {
        if ($agreementId <= 0 || get_post_type($agreementId) !== AgreementRegister::POST_TYPE) {
            return;
        }

        $current = self::normaliseIds(get_post_meta($agreementId, $metaKey, true));

        if (empty($current)) {
            return;
        }

        $updated = array_values(array_diff($current, [$relatedId]));

        if ($metaKey === 'estate_agreement_clients') {
            AgreementMeta::removeClientRole($agreementId, $relatedId);
        }

        if (empty($updated)) {
            delete_post_meta($agreementId, $metaKey);

            return;
        }

        update_post_meta($agreementId, $metaKey, $updated);
    }

    private static function normaliseIds($value): array
    {
        if (!is_array($value)) {
            $value = $value === '' ? [] : [$value];
        }

        $ids = array_filter(
            array_map(
                static fn($item) => is_scalar($item) ? (int) $item : 0,
                $value
            ),
            static fn($item) => $item > 0
        );

        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }
}
