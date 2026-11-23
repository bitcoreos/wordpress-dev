<?php
/**
 * Widget Framework
 *
 * Base widget class with registration system
 * Pattern extracted from userswp widget system
 *
 * @package BITCORE\Widgets
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Widgets;

/**
 * Widget Registration
 *
 * Handles widget registration.
 *
 * @since 1.0.0
 */
class WidgetRegistration {
    /**
     * Registered widgets.
     *
     * @var array<string,class-string<BaseWidget>>
     */
    private static array $widgets = [];

    /**
     * Register widget.
     *
     * @param class-string<BaseWidget> $widgetClass Widget class name.
     * @return void
     */
    public static function register(string $widgetClass): void {
        if (!is_subclass_of($widgetClass, BaseWidget::class)) {
            return;
        }

        self::$widgets[$widgetClass::getWidgetId()] = $widgetClass;
        add_action('widgets_init', [$widgetClass, 'register']);
    }

    /**
     * Get registered widgets.
     *
     * @return array<string,class-string<BaseWidget>> Registered widgets.
     */
    public static function getRegistered(): array {
        return self::$widgets;
    }
}

/**
 * Base Widget
 *
 * Abstract base class for widgets.
 *
 * @since 1.0.0
 */
abstract class BaseWidget extends \WP_Widget {
    /**
     * Widget ID.
     *
     * @var string
     */
    protected static string $widgetId = 'bitcore_base_widget';

    /**
     * Widget name.
     *
     * @var string
     */
    protected static string $widgetName = 'BITCORE Base Widget';

    /**
     * Widget description.
     *
     * @var string
     */
    protected static string $widgetDescription = 'Base widget class';

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct(
            static::$widgetId,
            __(static::$widgetName, 'bitcore-core'),
            [
                'description' => __(static::$widgetDescription, 'bitcore-core'),
                'classname' => 'bitcore-widget ' . static::$widgetId,
            ]
        );
    }

    /**
     * Get widget ID.
     *
     * @return string Widget ID.
     */
    public static function getWidgetId(): string {
        return static::$widgetId;
    }

    /**
     * Register widget.
     *
     * @return void
     */
    public static function register(): void {
        register_widget(static::class);
    }

    /**
     * {@inheritDoc}
     *
     * @param array<string,mixed> $args Widget arguments.
     * @param array<string,mixed> $instance Widget instance.
     * @return void
     */
    public function widget($args, $instance): void {
        echo $args['before_widget'] ?? '';
        
        if (!empty($instance['title'])) {
            echo ($args['before_title'] ?? '') . esc_html($instance['title']) . ($args['after_title'] ?? '');
        }
        
        $this->render($instance);
        
        echo $args['after_widget'] ?? '';
    }

    /**
     * Render widget content.
     *
     * @param array<string,mixed> $instance Widget instance.
     * @return void
     */
    abstract protected function render(array $instance): void;

    /**
     * {@inheritDoc}
     *
     * @param array<string,mixed> $instance Widget instance.
     * @param array<string,mixed> $newInstance New instance.
     * @return array<string,mixed> Updated instance.
     */
    public function update($newInstance, $oldInstance): array {
        $instance = [];
        $instance['title'] = !empty($newInstance['title']) ? sanitize_text_field($newInstance['title']) : '';
        return $instance;
    }

    /**
     * {@inheritDoc}
     *
     * @param array<string,mixed> $instance Widget instance.
     * @return string Widget form HTML.
     */
    public function form($instance): string {
        $title = !empty($instance['title']) ? esc_attr($instance['title']) : '';
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                <?php esc_html_e('Title:', 'bitcore-core'); ?>
            </label>
            <input 
                class="widefat" 
                id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                name="<?php echo esc_attr($this->get_field_name('title')); ?>"
                type="text" 
                value="<?php echo esc_attr($title); ?>"
            />
        </p>
        <?php
        return '';
    }
}

/**
 * Task Status Widget
 *
 * Displays task status summary.
 *
 * @since 1.0.0
 */
class TaskStatusWidget extends BaseWidget {
    /**
     * {@inheritDoc}
     */
    protected static string $widgetId = 'bitcore_task_status';

    /**
     * {@inheritDoc}
     */
    protected static string $widgetName = 'Task Status';

    /**
     * {@inheritDoc}
     */
    protected static string $widgetDescription = 'Display task status summary';

    /**
     * {@inheritDoc}
     */
    protected function render(array $instance): void {
        ?>
        <div class="bitcore-task-status">
            <ul>
                <li><?php esc_html_e('Pending: ', 'bitcore-core'); ?><span class="count">0</span></li>
                <li><?php esc_html_e('In Progress: ', 'bitcore-core'); ?><span class="count">0</span></li>
                <li><?php esc_html_e('Completed: ', 'bitcore-core'); ?><span class="count">0</span></li>
            </ul>
        </div>
        <?php
    }
}
