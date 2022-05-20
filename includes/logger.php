<?php
require_once 'utils.php';

class MobbexLogger
{
    public function __construct()
    {
        $this->error  = false;
        $this->helper = new MobbexHelper;

        if (!$this->helper->settings['api-key'] || !$this->helper->settings['access-token'])
            $this->error = MobbexLogger::notice('error', __('You need to specify an API Key and an Access Token.', 'mobbex-for-woocommerce'));

    }

    public function debug($message = 'debug', $data = [], $force = false)
    {
        if ($this->helper->settings['debug_mode'] != 'yes' && !$force)
            return;

        apply_filters(
            'simple_history_log',
            'Mobbex: ' . $message,
            $data,
            'debug'
        );
    }

    public static function notice($type, $msg)
    {
        add_action('admin_notices', function () use ($type, $msg) {
            $class = esc_attr("notice notice-$type");
            $msg = esc_html($msg);

            ob_start();

?>

            <div class="<?= $class ?>">
                <h2>Mobbex for Woocommerce</h2>
                <p><?= $msg ?></p>
            </div>

<?php

            echo ob_get_clean();
        });
    }
}