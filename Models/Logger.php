<?php
namespace Mobbex\WP\Checkout\Models;

class Logger
{
    /** @var \Mobbex\WP\Checkout\Models\Config */
    public $config;

    /**
     * Logger constructor.
     * 
     * @param array $settings Module configuration values.
     */
    public function __construct()
    {
        $this->config = new Config();
    }

    /**
     * Log a message to Simple History dashboard.
     * 
     * @param string $message Log message.
     * @param mixed $data Any extra data.
     * @param bool $force True to force log (bypass debug mode option).
     */
    public function debug($message = 'debug', $data = [], $force = false)
    {
        if (!$force && $this->config->debug_mode != 'yes')
            return;

        apply_filters(
            'simple_history_log',
            'Mobbex: ' . $message,
            $data,
            'debug'
        );
    }

    /**
     * Add a notice to the top of admin panel.
     * 
     * @param string $message The text to display in the notice.
     * @param string $type The name of the notice type. Can be error, success or notice.
     */
    public function notice($message, $type = 'error')
    {
        add_action('admin_notices', function () use ($message, $type) {
?>
            <div class="<?= esc_attr("notice notice-$type") ?>">
                <h2>Mobbex for Woocommerce</h2>
                <p><?= $message ?></p>
            </div>
<?php
        });
    }
}