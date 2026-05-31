<?php

namespace App\Console\Commands;

use App\Models\Team;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

#[Signature('flags:import')]
#[Description('Download national flag SVGs (from flagcdn.com) into public/flags, named by FIFA code.')]
class ImportFlags extends Command
{
    /**
     * Maps each FIFA three-letter code to its flagcdn code (ISO 3166-1 alpha-2, or a GB
     * subdivision for the home nations). Flags are served by flagcdn.com / flagpedia.net.
     *
     * @var array<string, string>
     */
    private const FLAGCDN_CODES = [
        'MEX' => 'mx', 'KOR' => 'kr', 'RSA' => 'za', 'CZE' => 'cz',
        'CAN' => 'ca', 'SUI' => 'ch', 'QAT' => 'qa', 'BIH' => 'ba',
        'BRA' => 'br', 'MAR' => 'ma', 'SCO' => 'gb-sct', 'HAI' => 'ht',
        'USA' => 'us', 'PAR' => 'py', 'AUS' => 'au', 'TUR' => 'tr',
        'GER' => 'de', 'ECU' => 'ec', 'CIV' => 'ci', 'CUW' => 'cw',
        'NED' => 'nl', 'JPN' => 'jp', 'TUN' => 'tn', 'SWE' => 'se',
        'BEL' => 'be', 'IRN' => 'ir', 'EGY' => 'eg', 'NZL' => 'nz',
        'ESP' => 'es', 'URU' => 'uy', 'KSA' => 'sa', 'CPV' => 'cv',
        'FRA' => 'fr', 'SEN' => 'sn', 'NOR' => 'no', 'IRQ' => 'iq',
        'ARG' => 'ar', 'AUT' => 'at', 'ALG' => 'dz', 'JOR' => 'jo',
        'POR' => 'pt', 'COL' => 'co', 'UZB' => 'uz', 'COD' => 'cd',
        'ENG' => 'gb-eng', 'CRO' => 'hr', 'PAN' => 'pa', 'GHA' => 'gh',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $directory = public_path('flags');
        File::ensureDirectoryExists($directory);

        $codes = Team::query()->whereNotNull('code')->pluck('code')->unique()->sort()->values();

        $imported = 0;
        $failed = 0;

        foreach ($codes as $code) {
            $flagcdn = self::FLAGCDN_CODES[$code] ?? null;

            if ($flagcdn === null) {
                $this->warn("No flagcdn mapping for {$code}; skipping (will use placeholder).");
                $failed++;

                continue;
            }

            $response = Http::timeout(20)->get("https://flagcdn.com/{$flagcdn}.svg");

            if (! $response->successful()) {
                $this->error("Failed to download {$code} ({$flagcdn}): HTTP {$response->status()}.");
                $failed++;

                continue;
            }

            File::put("{$directory}/{$code}.svg", $response->body());
            $imported++;
        }

        $this->components->info("Imported {$imported} flag(s) into public/flags".($failed > 0 ? "; {$failed} skipped/failed." : '.'));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
