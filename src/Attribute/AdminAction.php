<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Attribute;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class AdminAction
{
    public function __construct(
        public ?string $path = null,
        public ?string $routeName = null,
    ) {
    }
}
