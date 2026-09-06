<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Benchmark;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\Child;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\Inheritance\JoinedChildA;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\Inheritance\SingleTableChildA;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\Inheritance\SuperclassChildA;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\TestEntity;

/**
 * Measures what a test pays for the database refresh that RefreshDatabaseTrait
 * performs on each bootKernel(), for one combination of database platform and
 * cleanup method. The platform comes from DATABASE_URL, the cleanup method from
 * DB_CLEANUP_METHOD / DB_PURGE_MODE, so a single test class covers every cell of
 * the matrix; bin/benchmark.sh runs the combinations and collects the results.
 *
 * Each iteration boots the kernel, which refreshes the database, and then writes
 * a few records so the next refresh has something to clean up: a benchmark
 * against empty tables would measure the wrong thing, especially for DELETE
 * which is O(rows).
 *
 * The number of iterations can be set with BENCH_ITERATIONS, the file to write
 * the result to with BENCH_OUTPUT. The result contains the mean and the median
 * of the per-boot times: the first boot of a process also creates the database
 * and the schema, which would dominate a small sample.
 */
#[Group('benchmark')]
final class RefreshDatabaseBenchmarkTest extends KernelTestCase
{
    use RefreshDatabaseTrait;

    private const int DEFAULT_ITERATIONS = 50;

    /**
     * @var float[] milliseconds per bootKernel()
     */
    private static array $timings = [];

    public static function iterations(): iterable
    {
        $count = (int) ($_ENV['BENCH_ITERATIONS'] ?? self::DEFAULT_ITERATIONS);

        for ($i = 1; $i <= max(1, $count); ++$i) {
            yield "iteration $i" => [$i];
        }
    }

    #[DataProvider('iterations')]
    public function testRefresh(int $iteration): void
    {
        $start = microtime(true);
        self::bootKernel();
        self::$timings[] = (microtime(true) - $start) * 1000;

        $em = self::getContainer()->get('doctrine')->getManager();

        // leave rows behind for the next refresh to clean up
        $testEntity = new TestEntity();
        $em->persist($testEntity);

        $child = new Child();
        $child->testEntity = $testEntity;
        $em->persist($child);

        $em->persist(new SuperclassChildA());
        $em->persist(new SingleTableChildA());
        $em->persist(new JoinedChildA());
        $em->flush();

        self::assertNotNull($testEntity->id);
        self::assertSame($iteration, $iteration);
    }

    public static function tearDownAfterClass(): void
    {
        $timings = self::$timings;
        self::$timings = [];

        if ([] === $timings) {
            return;
        }

        // the first boot also creates the database and the schema
        $steadyState = \count($timings) > 1 ? \array_slice($timings, 1) : $timings;
        sort($steadyState);
        $count = \count($steadyState);

        $result = [
            'platform' => $_ENV['BENCH_PLATFORM'] ?? 'unknown',
            'cleanupMethod' => $_ENV['DB_CLEANUP_METHOD'] ?? 'purge',
            'purgeMode' => $_ENV['DB_PURGE_MODE'] ?? 'delete',
            'boots' => \count($timings),
            'firstBootMs' => round($timings[0], 1),
            'meanMs' => round(array_sum($steadyState) / $count, 1),
            'medianMs' => round(
                0 === $count % 2
                    ? ($steadyState[intdiv($count, 2) - 1] + $steadyState[intdiv($count, 2)]) / 2
                    : $steadyState[intdiv($count, 2)],
                1
            ),
            'minMs' => round($steadyState[0], 1),
            'maxMs' => round($steadyState[$count - 1], 1),
            'totalMs' => round(array_sum($timings), 1),
        ];

        $output = $_ENV['BENCH_OUTPUT'] ?? null;
        if (\is_string($output) && '' !== $output) {
            file_put_contents($output, json_encode($result, \JSON_PRETTY_PRINT)."\n");
        }
    }
}
