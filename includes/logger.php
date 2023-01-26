<?php

class MobbexLogger
{
    /** Module configuration settings */
    public $settings = [];

    /**
     * Logger constructor.
     * 
     * @param array $settings Module configuration values.
     */
    public function __construct($settings)
    {
        $this->settings = $settings;
    }

    /**
     * Log a message to Simple History dashboard.
     * 
     * Mode debug: Log data if debug mode is active
     * Mode error: Always log data.
     * Mode critical: Always log data & stop code execution.
     * 
     * @param string $mode debug | error | critical    
     * @param string $message
     * @param array $data
     */
    public function debug($mode, $message, $data = [])
    {
        if ($mode === 'debug' && $this->settings['debug_mode'] != 'yes')
            return;

        apply_filters(
            'simple_history_log',
            "Mobbex: " . $message,
            $data,
            $mode
        );

        if ($mode === 'critical')
            die;
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