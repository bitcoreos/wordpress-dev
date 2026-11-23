<?php
/**
 * Template Loader
 *
 * Template loading with theme override support
 * Pattern extracted from userswp class-templates.php
 *
 * @package BITCORE\Templates
 * @since 1.0.0
 * @version     1.0.0
 */

namespace BITCORE\Templates;

/**
 * Template Loader
 *
 * Handles template loading with override support.
 *
 * @since 1.0.0
 */
class TemplateLoader {
    /**
     * Template directory within plugin.
     *
     * @var string
     */
    private string $templateDir;

    /**
     * Theme template directory name.
     *
     * @var string
     */
    private string $themeTemplateDir;

    /**
     * Constructor.
     *
     * @param string $templateDir Plugin template directory.
     * @param string $themeTemplateDir Theme template directory name.
     */
    public function __construct(
        string $templateDir = BITCORE_PLUGIN_DIR . 'templates/',
        string $themeTemplateDir = 'bitcore'
    ) {
        $this->templateDir = trailingslashit($templateDir);
        $this->themeTemplateDir = $themeTemplateDir;
    }

    /**
     * Locate template file.
     *
     * @param string $templateName Template name.
     * @return string Template path.
     */
    public function locate(string $templateName): string {
        // Look in theme first
        $themeTemplate = locate_template([
            $this->themeTemplateDir . '/' . $templateName,
            $templateName,
        ]);

        if ($themeTemplate) {
            return $themeTemplate;
        }

        // Fall back to plugin template
        return $this->templateDir . $templateName;
    }

    /**
     * Load template.
     *
     * @param string $templateName Template name.
     * @param array<string,mixed> $args Template arguments.
     * @param bool $load Whether to load or return.
     * @return string|null Template content or null if loaded.
     */
    public function load(string $templateName, array $args = [], bool $load = true): ?string {
        $templatePath = $this->locate($templateName);

        if (!file_exists($templatePath)) {
            return null;
        }

        // Extract args
        if (!empty($args)) {
            extract($args, EXTR_SKIP);
        }

        if (!$load) {
            ob_start();
            include $templatePath;
            return ob_get_clean();
        }

        include $templatePath;
        return null;
    }

    /**
     * Get template part.
     *
     * @param string $slug Template slug.
     * @param string|null $name Template name suffix.
     * @param array<string,mixed> $args Template arguments.
     * @return void
     */
    public function getTemplatePart(string $slug, ?string $name = null, array $args = []): void {
        $templates = [];

        if ($name) {
            $templates[] = "{$slug}-{$name}.php";
        }
        $templates[] = "{$slug}.php";

        foreach ($templates as $template) {
            $templatePath = $this->locate($template);
            if (file_exists($templatePath)) {
                $this->load($template, $args);
                break;
            }
        }
    }

    /**
     * Check if template exists.
     *
     * @param string $templateName Template name.
     * @return bool True if exists.
     */
    public function exists(string $templateName): bool {
        return file_exists($this->locate($templateName));
    }
}
