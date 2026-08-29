<?php
declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';

final class FunctionsTest extends TestCase
{
    public function test_normaliser_maaned_normaliserer_kort_format(): void
    {
        $this->assertSame('2027-09', normaliser_maaned('2027-9'));
        $this->assertSame('2027-09', normaliser_maaned('2027-09'));
    }

    public function test_normaliser_maaned_returnerer_null_for_tom_vaerdi(): void
    {
        $this->assertNull(normaliser_maaned(''));
        $this->assertNull(normaliser_maaned(null));
    }

    public function test_normaliser_maaned_kaster_for_ukendt_vaerdi(): void
    {
        $this->expectException(InvalidArgumentException::class);
        normaliser_maaned('?');
    }

    public function test_normaliser_maaned_kaster_for_ugyldig_maaned(): void
    {
        $this->expectException(InvalidArgumentException::class);
        normaliser_maaned('2027-13');
    }

    public function test_formatMaaned_viser_dansk_maanedsnavn(): void
    {
        $this->assertSame('Marts 2026', formatMaaned('2026-03'));
        $this->assertSame('–', formatMaaned(null));
    }

    public function test_formatKrMio_bruger_mio_over_en_million(): void
    {
        $this->assertSame('1,5 mio. kr.', formatKrMio(1_500_000));
        $this->assertSame('500.000 kr.', formatKrMio(500_000));
        $this->assertSame('–', formatKrMio(null));
    }
}
