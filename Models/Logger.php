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
     * Mode debug: Log data if debug mode is active
     * Mode error: Always log data.
     * Mode critical: Always log data & stop code execution.
     * 
     * @param string $mode debug | error | fatal    
     * @param string $message
     * @param array $data
     */
    public function log($mode, $message, $data = [])
    {
        if ($mode === 'debug' && $this->config->debug_mode != 'yes')
            return;

        if($mode === 'fatal')
            $mode = 'critical';

        apply_filters(
            'simple_history_log',
            "Mobbex: " . $message,
            $data,
            $mode
        );

        if ($mode === 'critical')
            die($message);
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