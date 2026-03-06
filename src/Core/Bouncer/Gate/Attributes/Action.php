<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Bouncer\Gate\Attributes;

use Attribute;

/**
 * Declare an explicit action name for a controller method.
 *
 * This attribute allows explicit declaration of the action name that will
 * be used for permission checking, rather than relying on auto-resolution
 * from the controller and method names.
 *
 * ## Usage
 *
 * ```php
 * use Core\Bouncer\Gate\Attributes\Action;
 *
 * class ProductController
 * {
 *     #[Action('product.create')]
 *     public function store(Request $request)
 *     {
 *         // ...
 *     }
 *
 *     #[Action('product.delete', scope: 'product')]
 *     public function destroy(Product $product)
 *     {
 *         // ...
 *     }
 * }
 * ```
 *
 * ## Auto-Resolution
 *
 * If this attribute is not present, the action name is auto-resolved:
 * - `ProductController@store` -> `product.store`
 * - `Admin\UserController@index` -> `admin.user.index`
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class Action
{
    /**
     * Create a new Action attribute.
     *
     * @param  string  $name  The action identifier (e.g., 'product.create')
     * @param  string|null  $scope  Optional scope for resource-specific permissions
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $scope = null,
    ) {}
}
