<?php
include_once( dirname(__FILE__).ICWP_DS.'icwp_options_helper.php' );
include_once( dirname(__FILE__).ICWP_DS.'widgets'.ICWP_DS.'icwp_widgets.php' );
?>
<div class="wrap">
	<div class="bootstrap-wpadmin">
		<?php echo printOptionsPageHeader( __('Lockdown', 'wp-simple-firewall') ); ?>
		
		<div class="row">
			<div class="<?php echo $icwp_fShowAds? 'span9' : 'span12'; ?>">
			
				<form action="<?php echo $icwp_form_action; ?>" method="post" class="form-horizontal">
				<?php
					wp_nonce_field( $icwp_nonce_field );
					printAllPluginOptionsForm( $icwp_aAllOptions, $icwp_var_prefix, 1 );
				?>
				<div class="form-actions">
					<input type="hidden" name="<?php echo $icwp_var_prefix; ?>all_options_input" value="<?php echo $icwp_all_options_input; ?>" />
					<input type="hidden" name="icwp_plugin_form_submit" value="Y" />
					<button type="submit" class="btn btn-primary" name="submit"><?php _e( 'Save All Settings', 'wp-simple-firewall' ); ?></button>
					</div>
				</form>
				
			</div><!-- / span9 -->
		
			<?php if ( $icwp_fShowAds ) : ?>
			<div class="span3" id="side_widgets">
		  		<?php echo getWidgetIframeHtml('side-widgets-wtb'); ?>
			</div>
			<?php endif; ?>
		</div><!-- / row -->
	
	</div><!-- / bootstrap-wpadmin -->
	<?php include_once( dirname(__FILE__).'/include_js.php' ); ?>
</div>