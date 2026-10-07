<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\McpApp\Mcp;

use EasyCorp\Bundle\EasyAdminBundle\Mcp\McpDoctorCheckInterface;

final class TestDoctorCheck implements McpDoctorCheckInterface
{
    public function check(): iterable
    {
        yield [self::STATUS_WARNING, 'Test extension', 'Checked by an extension of the doctor command.'];
    }
}
