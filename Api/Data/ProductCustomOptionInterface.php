<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Ls\Hospitality\Api\Data;

use Magento\Catalog\Api\Data\ProductCustomOptionInterface as ParentProductCustomOptionInterface;

/**
 * @api
 * @since 100.0.2
 */
interface ProductCustomOptionInterface extends ParentProductCustomOptionInterface
{
    /**
     * LS Modifier and Recipe ID
     */
    const LS_MODIFIER_RECIPE_ID = 'ls_modifier_recipe_id';

    /**
     * Get LS Modifier and Recipe ID
     *
     * @return string
     */
    public function getLsModifierRecipeId();

    /**
     * LS Modifier and Recipe ID
     * @param string $lsModifierRecipeId
     * @return $this
     */
    public function setLsModifierRecipeId($lsModifierRecipeId);
    
}
