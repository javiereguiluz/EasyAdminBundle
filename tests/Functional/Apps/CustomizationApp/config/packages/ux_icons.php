<?php

// tests must not download icons from Iconify, so they use only local icon files
$container->loadFromExtension('ux_icons', [
    'icon_dir' => '%kernel.project_dir%/assets/icons',
    'iconify' => ['enabled' => false],
]);
