<?php
namespace Ezoic_Namespace;

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://ezoic.com
 * @since      1.0.0
 *
 * @package    Ezoic_Integration
 * @subpackage Ezoic_Integration/admin
 */
class Ezoic_AdsTxtManager_Settings {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $plugin_name The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $version The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version The version of this plugin.
	 *
	 * @since    1.0.0
	 *
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}


	/**
	 * Register and add settings
	 */
	public function initialize_adstxtmanager_settings()
	{
		add_settings_section(
				'ezoic_adstxtmanager_settings_section',
				__('Ezoic Ads.txt Manager', 'ezoic'),
				array($this, 'ezoic_adstxtmanager_settings_section_callback'),
				'ezoic_adstxtmanager'
		);

		add_settings_field(
				'ezoic_adstxtmanager_auto_detect',
				'Automatic Detection',
				array($this, 'ezoic_adstxtmanager_auto_detect_field'),
				'ezoic_adstxtmanager',
				'ezoic_adstxtmanager_settings_section'
		);

		add_settings_field(
				'ezoic_adstxtmanager_id',
				'Ads.txt Manager ID',
				array($this, 'ezoic_adstxtmanager_id_field'),
				'ezoic_adstxtmanager',
				'ezoic_adstxtmanager_settings_section'
		);

		if (get_option('ezoic_adstxtmanager_status') == false) {
			update_option('ezoic_adstxtmanager_status', array('status' => false, 'message' => ''));
		}

		register_setting(
				'ezoic_adstxtmanager',
				'ezoic_adstxtmanager_status',
				array('type'=> 'array', 'default' => array('status' => false, 'message' => ''))
		);

		register_setting(
				'ezoic_adstxtmanager',
				'ezoic_adstxtmanager_id',
				array('default' => 0, 'type' => 'integer', 'sanitize_callback' => array($this, 'sanitize_adstxtmanager_id'))
		);

		register_setting(
				'ezoic_adstxtmanager',
				'ezoic_adstxtmanager_auto_detect',
				array('default' => true)
		);
	}

	/**
	 * Empty Callback for WordPress Settings
	 *
	 * @return void
	 * @since 1.0.0
	 */
	function ezoic_adstxtmanager_settings_section_callback() {
		?>
		<?php if ( ! get_option( 'permalink_structure' ) ) : ?>
			<div class="notice notice-error adstxtmanager_activate">
				<p class="adstxtmanager_description">
					<?php _e( 'Ezoic\'s Ads.txt redirection does not work with the WordPress \'Plain\' permalink structure. Please change to a different <a href="' . get_admin_url( null,
									'options-permalink.php' ) . '">permalink URL structure</a> (such as \'Post name\').', 'ezoic' ); ?>
				</p>
			</div>
		<?php endif; ?>
		<p>
			<?php _e( 'In order for Ezoic to manage your ads.txt file, you are required to set up a redirection from your websites\' ads.txt file to <a href="' . EZOIC_ADSTXT_MANAGER__SITE . '" target="_blank"><strong>Ads.txt Manager</strong></a> (an Ezoic product).',
					'ezoic' ); ?>
		</p>
		<p><?php _e( 'Enable Automatic Detection, or enter your Ads.txt Manager ID number below, and the ads.txt redirection will be automatically setup for you.' ); ?></p>
		<hr/>
		<?php
	}

	function ezoic_adstxtmanager_id_field() {
		$adstxtmanager_id = Ezoic_AdsTxtManager::ezoic_adstxtmanager_id(true);
		$auto_detect = Ezoic_AdsTxtManager::ezoic_adstxtmanager_auto_detect();
		?>
		<input type="text" name="ezoic_adstxtmanager_id" class="regular-text code"
			   value="<?php echo $adstxtmanager_id; ?>"
			<?php echo $auto_detect ? 'disabled' : ''; ?>
		/>
		<?php if ($auto_detect): ?>
			<p class="description">
				<span class="dashicons dashicons-info" style="color: #0073aa;"></span>
				The Ads.txt Manager ID is automatically set when auto-detect is enabled.<br/>
				To change it, please log into your <a href="https://pubdash.ezoic.com/ezoicads/adtransparency" target="_blank">Publisher Dashboard</a>.
			</p>
		<?php elseif ($adstxtmanager_id === 19390): ?>
			<p class="description">
				<span class="dashicons dashicons-yes" style="color: #46b450;"></span>
				You are using Ezoic's automatic ads.txt setup.
			</p>
		<?php else: ?>
			<p class="description">
				You can find your <a href="https://svc.adstxtmanager.com/settings" target="_blank">Ads.txt Manager ID here</a>.
			</p>
		<?php endif; ?>
		<?php
	}

	function ezoic_adstxtmanager_auto_detect_field() {
		$value = Ezoic_AdsTxtManager::ezoic_adstxtmanager_auto_detect();
		?>
		<input type="radio" id="ezoic_adstxtmanager_auto_detect_on" name="ezoic_adstxtmanager_auto_detect" value="on"
				<?php
				if ( $value ) {
					echo( 'checked="checked"' );
				}
				?>
		/>
		<label for="ezoic_adstxtmanager_auto_detect_on">Enabled</label>

		<input type="radio" id="ezoic_adstxtmanager_auto_detect_off" name="ezoic_adstxtmanager_auto_detect" value="off"
				<?php
				if ( ! $value ) {
					echo( 'checked="checked"' );
				}
				?>
		/>
		<label for="ezoic_adstxtmanager_auto_detect_off">Disabled</label>
		<p class="description">
			Automatically sets your Ads.txt Manager ID that is linked to Ezoic. <br/><em>*Recommend enabling</em>
		</p>
		<?php
	}

	public function sanitize_adstxtmanager_id($input) {
		$new_input = 0;
		if(isset($input)) {
			$new_input = absint($input);
		}
		return $new_input;
	}
}

?>
