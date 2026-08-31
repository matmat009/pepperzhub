<?php

namespace Tests\Feature\Storefront;

use Tests\TestCase;

/**
 * No storefront page may ship a link that goes nowhere.
 *
 * `href="#"` renders as a fully styled, clickable, focusable link that jumps to
 * the top of the page — indistinguishable to a customer from a real link that
 * is broken. The Order Confirmation page carried two of them ("Message on
 * Facebook" / "Message on WhatsApp") telling someone who had just paid to take
 * an action that did nothing.
 *
 * Source contract rather than a rendered assertion, matching
 * ProductDescriptionTest: there is no Vue test runner in this repository.
 */
class DeadLinkTest extends TestCase
{
    /**
     * Files with dead links that are known and deliberately still outstanding.
     *
     * StorefrontFooter.vue has three — the Facebook, Instagram and TikTok icons
     * — which were out of scope for Phase 2.2 and need real handles from the
     * owner before they can point anywhere. Listed rather than skipped over, so
     * a new dead link in any other component still fails this test, and so
     * removing these from the list is all it takes to lock them down.
     *
     * @var list<string>
     */
    private const KNOWN_OUTSTANDING = [
        'components/storefront/StorefrontFooter.vue',
    ];

    public function test_no_storefront_page_renders_a_dead_link(): void
    {
        $offenders = [];

        foreach ($this->storefrontSources() as $relative => $path) {
            if (in_array($relative, self::KNOWN_OUTSTANDING, true)) {
                continue;
            }

            if ($this->hasDeadLink(file_get_contents($path))) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame([], $offenders, 'dead href="#" links found in: '.implode(', ', $offenders));
    }

    /** The known-outstanding list must not rot into a list of already-fixed files. */
    public function test_the_known_outstanding_list_is_still_accurate(): void
    {
        $sources = $this->storefrontSources();

        foreach (self::KNOWN_OUTSTANDING as $relative) {
            $this->assertArrayHasKey($relative, $sources, "{$relative} no longer exists");
            $this->assertTrue(
                $this->hasDeadLink(file_get_contents($sources[$relative])),
                "{$relative} has no dead links left — drop it from KNOWN_OUTSTANDING.",
            );
        }
    }

    /**
     * Attribute lines only.
     *
     * StorefrontNav.vue's header comment quotes `href="#"` while explaining that
     * two placeholder nav items were removed; matching raw substrings anywhere
     * in the file would flag that as a defect.
     */
    private function hasDeadLink(string $contents): bool
    {
        foreach (preg_split('/\R/', $contents) as $line) {
            if (preg_match('/^\s*(?::?href)=(["\'])#\1/', $line)) {
                return true;
            }
        }

        return (bool) preg_match('/<a[^>]*\shref=(["\'])#\1/', $contents);
    }

    /**
     * @return array<string, string> relative path => absolute path
     */
    private function storefrontSources(): array
    {
        $roots = [
            'pages/storefront',
            'components/storefront',
            'layouts',
        ];

        $files = [];

        foreach ($roots as $root) {
            $absolute = resource_path('js/'.$root);

            if (! is_dir($absolute)) {
                continue;
            }

            /** @var iterable<\SplFileInfo> $iterator */
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($absolute, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'vue') {
                    $relative = $root.'/'.str_replace(
                        DIRECTORY_SEPARATOR,
                        '/',
                        substr($file->getPathname(), strlen($absolute) + 1),
                    );

                    $files[$relative] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
