<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Tests;

use Iamshoaibktk\Geolens\Llms\Renderer;
use PHPUnit\Framework\TestCase;

final class LlmsTest extends TestCase
{
    public function testShapeFollowsTheSpecification(): void
    {
        $out = Renderer::render('My Shop', 'We sell tees.', [
            ['title' => 'Categories', 'links' => [
                ['name' => 'Men', 'url' => 'https://s/3-men', 'note' => 'Shirts and more'],
                ['name' => 'Women', 'url' => 'https://s/4-women'],
            ]],
        ]);
        $this->assertSame(
            "# My Shop\n\n> We sell tees.\n\n## Categories\n\n- [Men](https://s/3-men): Shirts and more\n- [Women](https://s/4-women)\n",
            $out
        );
    }

    public function testOnlyTheTitleIsRequired(): void
    {
        $this->assertSame("# My Shop\n", Renderer::render('My Shop', '', []));
    }

    public function testEmptySectionsAreSkipped(): void
    {
        $this->assertStringNotContainsString('##', Renderer::render('S', '', [['title' => 'Empty', 'links' => []]]));
    }

    public function testShopTextCannotBreakTheFormat(): void
    {
        $out = Renderer::render("My\nShop", "Line one\n\n## Fake section", [
            ['title' => 'A', 'links' => [['name' => 'x](http://evil) [y', 'url' => 'https://s/a b(c)', 'note' => "n\n- [z](u)"]]],
        ]);
        $this->assertStringStartsWith("# My Shop\n", $out);
        $this->assertSame(1, substr_count($out, "\n## "));
        $this->assertStringContainsString('[x)(http://evil) (y](https://s/a%20b%28c%29)', $out);
        $this->assertSame(1, substr_count($out, "\n- "));
    }

    public function testFileStaysUnderTheLimit(): void
    {
        $links = [];
        for ($i = 0; $i < 400; ++$i) {
            $links[] = ['name' => 'Category ' . $i, 'url' => 'https://shop.example/' . $i . '-category', 'note' => str_repeat('n', 100)];
        }
        $out = Renderer::render('S', '', [
            ['title' => 'First', 'links' => [['name' => 'a', 'url' => 'https://s/a']]],
            ['title' => 'Huge', 'links' => $links],
            ['title' => 'Last', 'links' => [['name' => 'b', 'url' => 'https://s/b']]],
        ]);
        $this->assertLessThanOrEqual(Renderer::MAX_CHARS, strlen($out));
        $this->assertStringContainsString('## First', $out);
        $this->assertStringNotContainsString('## Huge', $out);
    }

    public function testNoteStripsMarkupAndCutsAtAWord(): void
    {
        $this->assertSame('Soft cotton tee', Renderer::note('<p>Soft <b>cotton</b> tee</p>'));
        $note = Renderer::note('<p>' . str_repeat('word ', 60) . '</p>', 50);
        $this->assertLessThanOrEqual(53, mb_strlen($note));
        $this->assertStringEndsWith('word...', $note);
    }
}
