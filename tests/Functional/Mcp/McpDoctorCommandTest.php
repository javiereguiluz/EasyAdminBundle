<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Mcp;

use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\McpApp\Kernel;
use Symfony\AI\McpBundle\McpBundle;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class McpDoctorCommandTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testDoctor(): void
    {
        $commandTester = new CommandTester((new Application(self::bootKernel()))->find('easyadmin:mcp:doctor'));
        $exitCode = $commandTester->execute([]);
        $output = $commandTester->getDisplay();

        if (!class_exists(McpBundle::class)) {
            $this->assertSame(Command::FAILURE, $exitCode);
            $this->assertStringContainsString('Not installed. Run: composer require symfony/mcp-bundle', $output);

            return;
        }

        $this->assertSame(Command::SUCCESS, $exitCode, $output);
        $this->assertStringContainsString('selected mode', $output);
        $this->assertStringContainsString('/admin/mcp', $output);
        $this->assertStringContainsString('"mcp" (stateless)', $output);
        $this->assertStringContainsString('IS_AUTHENTICATED_FULLY', $output);
        // added by a service that implements McpDoctorCheckInterface
        $this->assertStringContainsString('Checked by an extension of the doctor command.', $output);
    }
}
