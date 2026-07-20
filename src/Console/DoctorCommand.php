<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Console;

use Cbox\LaravelPostal\Support\Doctor;
use Cbox\LaravelPostal\Support\DoctorCheck;
use Cbox\LaravelPostal\Support\DoctorStatus;
use Illuminate\Console\Command;

class DoctorCommand extends Command
{
    protected $signature = 'postal:doctor {--no-ping : Skip live connectivity checks}';

    protected $description = 'Diagnose the Postal setup: servers, signing key, routes, store tables, mailer and connectivity';

    public function handle(Doctor $doctor): int
    {
        $checks = $doctor->run(ping: ! (bool) $this->option('no-ping'));

        $section = null;

        foreach ($checks as $check) {
            if ($check->section !== $section) {
                $section = $check->section;
                $this->newLine();
                $this->line('  <options=bold>'.ucfirst($section).'</>');
            }

            $this->line('  '.$this->badge($check->status)." {$check->name}".($check->message !== '' ? " — {$check->message}" : ''));
        }

        $failures = count(array_filter($checks, static fn (DoctorCheck $check): bool => $check->status === DoctorStatus::Failure));
        $warnings = count(array_filter($checks, static fn (DoctorCheck $check): bool => $check->status === DoctorStatus::Warning));

        $this->newLine();

        if ($failures > 0) {
            $this->components->error("{$failures} failure(s), {$warnings} warning(s) — fix the failures above and re-run postal:doctor.");

            return self::FAILURE;
        }

        if ($warnings > 0) {
            $this->components->warn("All checks passed with {$warnings} warning(s).");
        } else {
            $this->components->info('Everything looks healthy.');
        }

        return self::SUCCESS;
    }

    private function badge(DoctorStatus $status): string
    {
        return match ($status) {
            DoctorStatus::Ok => '<info>✓</info>',
            DoctorStatus::Warning => '<comment>⚠</comment>',
            DoctorStatus::Failure => '<error>✗</error>',
        };
    }
}
