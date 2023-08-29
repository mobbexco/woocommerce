<?php
namespace Mobbex\WP\Checkout\Model;

class Logger
{
    /** @var \Mobbex\WP\Checkout\Model\Config */
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
     * Save a message in mobbex log table.
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
        
        // Save log in database
        (new \Mobbex\WP\Checkout\Model\Db)->insert(
            'mobbex_log', ['type' => $mode, 'message'=> $message, 'data' => json_encode($data), 'creation_date' => date("Y-m-d h:i:sa")]
            );

        if($mode === 'fatal')
            $mode = 'critical';


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