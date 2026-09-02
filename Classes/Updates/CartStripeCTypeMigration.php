<?php

declare(strict_types=1);

namespace Schnitzler\CartStripe\Updates;

use TYPO3\CMS\Core\Attribute\UpgradeWizard;
use TYPO3\CMS\Core\Upgrades\AbstractListTypeToCTypeUpdate;

#[UpgradeWizard('cartStripeCTypeMigration')]
final class CartStripeCTypeMigration extends AbstractListTypeToCTypeUpdate
{
    public function getTitle(): string
    {
        return 'Migrate "Schnitzler CartStripe" plugins to content elements.';
    }

    public function getDescription(): string
    {
        return 'The "Schnitzler CartStripe" plugins are now registered as content element. Update migrates existing records and backend user permissions.';
    }

    /**
     * This must return an array containing the "list_type" to "CType" mapping.
     *
     *  Example:
     *
     *  [
     *      'pi_plugin1' => 'pi_plugin1',
     *      'pi_plugin2' => 'new_content_element',
     *  ]
     *
     * @return array<string, string>
     */
    protected function getListTypeToCTypeMapping(): array
    {
        return [
            'cartstripe_cart' => 'cartstripe_cart',
        ];
    }
}
