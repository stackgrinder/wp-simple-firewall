<?php
/**
 * Copyright (c) 2014 iControlWP <support@icontrolwp.com>
 * All rights reserved.
 * 
 * Version: 2013-11-15-V1
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

if ( !class_exists('ICWP_OptionsHandler_Base_V2') ):

class ICWP_OptionsHandler_Base_V2 {

	/**
	 * @var ICWP_Wordpress_Simple_Firewall_Plugin
	 */
	protected $oPluginVo;

	/**
	 * @var string
	 */
	const CollateSeparator = '--SEP--';
	/**
	 * @var string
	 */
	const PluginVersionKey = 'current_plugin_version';
	
	/**
	 * @var boolean
	 */
	protected $fNeedSave;
	
	/**
	 * @var array
	 */
	protected $m_aOptions;

	/**
	 * These are options that need to be stored, but are never set by the UI.
	 * 
	 * @var array
	 */
	protected $m_aNonUiOptions;

	/**
	 * @var array
	 */
	protected $m_aOptionsValues;
	
	/**
	 * @var string
	 */
	protected $sOptionsStoreKey;
	
	/**
	 * @var array
	 */
	protected $aOptionsKeys;

	/**
	 * @var string
	 */
	protected $sFeatureName;
	/**
	 * @var string
	 */
	protected $sFeatureSlug;
	/**
	 * @var string
	 */
	protected $fShowFeatureMenuItem = true;

	public function __construct( $oPluginVo, $sOptionsStoreKey ) {
		$this->oPluginVo = $oPluginVo;
		$this->sOptionsStoreKey = $this->prefixOptionKey( $sOptionsStoreKey );

		// Handle any upgrades as necessary (only go near this if it's the admin area)
		add_action( 'init', array( $this, 'onWpInit' ), 1 );
		add_action( $this->doPrefix( 'form_submit', '_' ), array( $this, 'updatePluginOptionsFromSubmit' ) );
		add_filter( $this->doPrefix( 'filter_plugin_submenu_items', '_' ), array( $this, 'filter_addPluginSubMenuItem' ) );
	}

	/**
	 * @param $aItems
	 * @return mixed
	 */
	public function filter_addPluginSubMenuItem( $aItems ) {
		if ( !$this->fShowFeatureMenuItem || empty($this->sFeatureName) ) {
			return $aItems;
		}
		$sMenuPageTitle = $this->oPluginVo->getHumanName().' - '.$this->sFeatureName;
		$aItems[ $sMenuPageTitle ] = array(
			$this->sFeatureName,
			$this->doPrefix( $this->sFeatureSlug ),
			'onDisplayAll'
		);
		return $aItems;
	}
	
	/**
	 * A action added to WordPress 'plugins_loaded' hook
	 */
	public function onWpInit() {
		$this->doUpdates();
	}
	
	protected function doUpdates() {
		if ( $this->hasPluginManageRights() ) {
			$this->buildOptions();
			$this->updateHandler();
		}
	}

	/**
	 * @return bool
	 */
	public function hasPluginManageRights() {
		if ( !current_user_can( $this->oPluginVo->getBasePermissions() ) ) {
			return false;
		}

		$oWpFunc = $this->loadWpFunctions();
		if ( is_admin() && !$oWpFunc->isMultisite() ) {
			return true;
		}
		else if ( is_network_admin() && $oWpFunc->isMultisite() ) {
			return true;
		}
		return false;
	}

	/**
	 * @return string
	 */
	public function getVersion() {
		$sVersion = $this->getOpt( self::PluginVersionKey );
		return empty( $sVersion )? '0.0' : $sVersion;
	}

	/**
	 * Gets the array of all possible options keys
	 * 
	 * @return array
	 */
	public function getOptionsKeys() {
		$this->setOptionsKeys();
		return $this->aOptionsKeys;
	}
	
	/**
	 * @return void
	 */
	public function setOptionsKeys() {
		if ( !empty( $this->aOptionsKeys ) ) {
			return;
		}
		$this->buildOptions();
	}
	
	/**
	 * Determines whether the given option key is a valid option
	 *
	 * @param string
	 * @return boolean
	 */
	public function getIsOptionKey( $sOptionKey ) {
		if ( $sOptionKey == self::PluginVersionKey ) {
			return true;
		}
		$this->setOptionsKeys();
		return ( in_array( $sOptionKey, $this->aOptionsKeys ) );
	}
	
	/**
	 * Sets the value for the given option key
	 * 
	 * @param string $insKey
	 * @param mixed $inmValue
	 * @return boolean
	 */
	public function setOpt( $insKey, $inmValue ) {
		
		if ( !$this->getIsOptionKey( $insKey ) ) {
			return false;
		}
		
		if ( !isset( $this->m_aOptionsValues ) ) {
			$this->loadStoredOptionsValues();
		}
		
		if ( $this->getOpt( $insKey ) === $inmValue ) {
			return true;
		}
		
		$this->m_aOptionsValues[ $insKey ] = $inmValue;
		
		if ( !$this->fNeedSave ) {
			$this->fNeedSave = true;
		}
		return true;
	}

	/**
	 * @param string $insKey
	 * @return Ambigous <boolean, multitype:>
	 */
	public function getOpt( $insKey ) {
		if ( !isset( $this->m_aOptionsValues ) ) {
			$this->loadStoredOptionsValues();
		}
		return ( isset( $this->m_aOptionsValues[ $insKey ] )? $this->m_aOptionsValues[ $insKey ] : false );
	}
	
	/**
	 * Retrieves the full array of options->values
	 * 
	 * @return array
	 */
	public function getOptions() {
		$this->buildOptions();
		return $this->m_aOptions;
	}

	/**
	 * Loads the options and their stored values from the WordPress Options store.
	 *
	 * @return array
	 */
	public function getPluginOptionsValues() {
		$this->generateOptionsValues();
		return $this->m_aOptionsValues;
	}
	
	/**
	 * Saves the options to the WordPress Options store.
	 * 
	 * It will also update the stored plugin options version.
	 */
	public function savePluginOptions() {
		
		$this->doPrePluginOptionsSave();
		$this->updateOptionsVersion();
		if ( !$this->fNeedSave ) {
			return true;
		}

		$oWpFunc = $this->loadWpFunctions();
		$oWpFunc->updateOption( $this->sOptionsStoreKey, $this->m_aOptionsValues );
		$this->fNeedSave = false;
	}
	
	public function collateAllFormInputsForAllOptions() {

		if ( !isset( $this->m_aOptions ) ) {
			$this->buildOptions();
		}
		
		$aToJoin = array();
		foreach ( $this->m_aOptions as $aOptionsSection ) {
			
			if ( empty( $aOptionsSection ) ) {
				continue;
			}
			foreach ( $aOptionsSection['section_options'] as $aOption ) {
				list($sKey, $fill1, $fill2, $sType) =  $aOption;
				$aToJoin[] = (is_array($sType) ? array_shift($sType): $sType).':'.$sKey;
			}
		}
		return implode( self::CollateSeparator, $aToJoin );
	}
	
	/**
	 * @return array
	 */
	protected function generateOptionsValues() {
		if ( !isset( $this->m_aOptionsValues ) ) {
			$this->loadStoredOptionsValues();
		}
		if ( empty( $this->m_aOptionsValues ) ) {
			$this->buildOptions();	// set the defaults
		}
	}
	
	/**
	 * Loads the options and their stored values from the WordPress Options store.
	 */
	protected function loadStoredOptionsValues() {
		if ( empty( $this->m_aOptionsValues ) ) {
			$oWpFunc = $this->loadWpFunctions();
			$this->m_aOptionsValues = $oWpFunc->getOption( $this->sOptionsStoreKey, array() );
			if ( empty( $this->m_aOptionsValues ) ) {
				$this->fNeedSave = true;
			}
		}
	}
	
	protected function defineOptions() {
		
		if ( !empty( $this->m_aOptions ) ) {
			return true;
		}
		
		$aMisc = array(
			'section_title' => 'Miscellaneous Plugin Options',
			'section_options' => array(
				array(
					'delete_on_deactivate',
					'',
					'N',
					'checkbox',
					'Delete Plugin Settings',
					'Delete All Plugin Settings Upon Plugin Deactivation',
					'Careful: Removes all plugin options when you deactivite the plugin.'
				),
			),
		);
		$this->m_aOptions = array( $aMisc );
	}

	/**
	 * Will initiate the plugin options structure for use by the UI builder.
	 * 
	 * It will also fill in $this->m_aOptionsValues with defaults where appropriate.
	 * 
	 * It doesn't set any values, just populates the array created in buildOptions()
	 * with values stored.
	 * 
	 * It has to handle the conversion of stored values to data to be displayed to the user.
	 * 
	 * @param string $insUpdateKey - if only want to update a single key, supply it here.
	 */
	public function buildOptions() {

		$this->defineOptions();
		$this->loadStoredOptionsValues();

		$this->aOptionsKeys = array();
		foreach ( $this->m_aOptions as &$aOptionsSection ) {
			
			if ( empty( $aOptionsSection ) || !isset( $aOptionsSection['section_options'] ) ) {
				continue;
			}
			
			foreach ( $aOptionsSection['section_options'] as &$aOptionParams ) {
				
				list( $sOptionKey, $sOptionValue, $sOptionDefault, $sOptionType ) = $aOptionParams;

				$this->aOptionsKeys[] = $sOptionKey;

				if ( $this->getOpt( $sOptionKey ) === false ) {
					$this->setOpt( $sOptionKey, $sOptionDefault );
				}
				$mCurrentOptionVal = $this->getOpt( $sOptionKey );
				
				if ( $sOptionType == 'password' && !empty( $mCurrentOptionVal ) ) {
					$mCurrentOptionVal = '';
				}
				else if ( $sOptionType == 'ip_addresses' ) {
					
					if ( empty( $mCurrentOptionVal ) ) {
						$mCurrentOptionVal = '';
					}
					else {
						$mCurrentOptionVal = implode( "\n", $this->convertIpListForDisplay( $mCurrentOptionVal ) );
					}
				}
				else if ( $sOptionType == 'yubikey_unique_keys' ) {

					if ( empty( $mCurrentOptionVal ) ) {
						$mCurrentOptionVal = '';
					}
					else {
						$aDisplay = array();
						foreach( $mCurrentOptionVal as $aParts ) {
							$aDisplay[] = key($aParts) .', '. reset($aParts);
						}
						$mCurrentOptionVal = implode( "\n", $aDisplay );
					}
				}
				else if ( $sOptionType == 'comma_separated_lists' ) {
					
					if ( empty( $mCurrentOptionVal ) ) {
						$mCurrentOptionVal = '';
					}
					else {
						$aNewValues = array();
						foreach( $mCurrentOptionVal as $sPage => $aParams ) {
							$aNewValues[] = $sPage.', '. implode( ", ", $aParams );
						}
						$mCurrentOptionVal = implode( "\n", $aNewValues );
					}
				}
				$aOptionParams[1] = $mCurrentOptionVal;
			}
		}
		
		// Cater for Non-UI options that don't necessarily go through the UI
		if ( isset($this->m_aNonUiOptions) && is_array($this->m_aNonUiOptions) ) {
			foreach( $this->m_aNonUiOptions as $sOption ) {
				$this->aOptionsKeys[] = $sOption;
				if ( !$this->getOpt( $sOption ) ) {
					$this->setOpt( $sOption, '' );
				}
			}
		}
	}
	
	/**
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() { }

	/**
	 */
	protected function updateOptionsVersion() {
		$this->setOpt( self::PluginVersionKey, $this->oPluginVo->getVersion() );
	}
	
	/**
	 * Deletes all the options including direct save.
	 */
	public function deletePluginOptions() {
		$oWpFunc = $this->loadWpFunctions();
		$oWpFunc->deleteOption( $this->sOptionsStoreKey );
	}

	protected function convertIpListForDisplay( $inaIpList = array() ) {

		$aDisplay = array();
		if ( empty( $inaIpList ) || empty( $inaIpList['ips'] ) ) {
			return $aDisplay;
		}
		foreach( $inaIpList['ips'] as $sAddress ) {
			// offset=1 in the case that it's a range and the first number is negative on 32-bit systems
			$mPos = strpos( $sAddress, '-', 1 );
			
			if ( $mPos === false ) { //plain IP address
				$sDisplayText = long2ip( $sAddress );
			}
			else {
				//we remove the first character in case this is '-'
				$aParts = array( substr( $sAddress, 0, 1 ), substr( $sAddress, 1 ) );
				list( $nStart, $nEnd ) = explode( '-', $aParts[1], 2 );
				$sDisplayText = long2ip( $aParts[0].$nStart ) .'-'. long2ip( $nEnd );
			}
			$sLabel = $inaIpList['meta'][ md5($sAddress) ];
			$sLabel = trim( $sLabel, '()' );
			$aDisplay[] = $sDisplayText . ' ('.$sLabel.')';
		}
		return $aDisplay;
	}

	/**
	 * @param string $sAllOptionsInput - comma separated list of all the input keys to be processed from the $_POST
	 * @return void|boolean
	 */
	public function updatePluginOptionsFromSubmit( $sAllOptionsInput ) {
		if ( empty( $sAllOptionsInput ) ) {
			return;
		}
		$this->loadDataProcessor();
		$this->loadStoredOptionsValues();
		
		$aAllInputOptions = explode( self::CollateSeparator, $sAllOptionsInput );
		foreach ( $aAllInputOptions as $sInputKey ) {
			$aInput = explode( ':', $sInputKey );
			list( $sOptionType, $sOptionKey ) = $aInput;
			
			if ( !$this->getIsOptionKey( $sOptionKey ) ) {
				continue;
			}

			$sOptionValue = ICWP_WPSF_DataProcessor::FetchPost( $this->prefixOptionKey( $sOptionKey ) );
			if ( is_null($sOptionValue) ) {
	
				if ( $sOptionType == 'text' || $sOptionType == 'email' ) { //if it was a text box, and it's null, don't update anything
					continue;
				}
				else if ( $sOptionType == 'checkbox' ) { //if it was a checkbox, and it's null, it means 'N'
					$sOptionValue = 'N';
				}
				else if ( $sOptionType == 'integer' ) { //if it was a integer, and it's null, it means '0'
					$sOptionValue = 0;
				}
			}
			else { //handle any pre-processing we need to.
	
				if ( $sOptionType == 'integer' ) {
					$sOptionValue = intval( $sOptionValue );
				}
				else if ( $sOptionType == 'password' && $this->hasEncryptOption() ) { //md5 any password fields
					$sTempValue = trim( $sOptionValue );
					if ( empty( $sTempValue ) ) {
						continue;
					}
					$sOptionValue = md5( $sTempValue );
				}
				else if ( $sOptionType == 'ip_addresses' ) { //ip addresses are textareas, where each is separated by newline
					$sOptionValue = ICWP_WPSF_DataProcessor::ExtractIpAddresses( $sOptionValue );
				}
				else if ( $sOptionType == 'yubikey_unique_keys' ) { //ip addresses are textareas, where each is separated by newline and are 12 chars long
					$sOptionValue = ICWP_WPSF_DataProcessor::CleanYubikeyUniqueKeys( $sOptionValue );
				}
				else if ( $sOptionType == 'email' && function_exists( 'is_email' ) && !is_email( $sOptionValue ) ) {
					$sOptionValue = '';
				}
				else if ( $sOptionType == 'comma_separated_lists' ) {
					$sOptionValue = ICWP_WPSF_DataProcessor::ExtractCommaSeparatedList( $sOptionValue );
				}
			}
			$this->setOpt( $sOptionKey, $sOptionValue );
		}
		return $this->savePluginOptions( true );
	}
	
	/**
	 * Should be over-ridden by each new class to handle upgrades.
	 * 
	 * Called upon construction and after plugin options are initialized.
	 */
	protected function updateHandler() {
//		if ( version_compare( $sCurrentVersion, '2.3.0', '<=' ) ) { }
	}
	
	/**
	 * @param array $inaNewOptions
	 */
	protected function mergeNonUiOptions( $inaNewOptions = array() ) {

		if ( !empty( $this->m_aNonUiOptions ) ) {
			$this->m_aNonUiOptions = array_merge( $this->m_aNonUiOptions, $inaNewOptions );
		}
		else {
			$this->m_aNonUiOptions = $inaNewOptions;
		}
	}

	/**
	 * @return boolean
	 */
	public function hasEncryptOption() {
		return function_exists( 'md5' );
	//	return extension_loaded( 'mcrypt' );
	}
	
	protected function getVisitorIpAddress( $infAsLong = true ) {
		$this->loadDataProcessor();
		return ICWP_WPSF_DataProcessor::GetVisitorIpAddress( $infAsLong );
	}

	/**
	 * Prefixes an option key only if it's needed
	 *
	 * @param $sKey
	 * @return string
	 */
	protected function prefixOptionKey( $sKey ) {
		return $this->doPrefix( $sKey, '_' );
	}

	/**
	 * Will prefix and return any string with the unique plugin prefix.
	 *
	 * @param string $sSuffix
	 * @param string $sGlue
	 * @return string
	 */
	public function doPrefix( $sSuffix = '', $sGlue = '-' ) {
		$sPrefix = $this->oPluginVo->getFullPluginPrefix( $sGlue );

		if ( $sSuffix == $sPrefix || strpos( $sSuffix, $sPrefix.$sGlue ) === 0 ) { //it already has the prefix
			return $sSuffix;
		}

		return sprintf( '%s%s%s', $sPrefix, empty($sSuffix)? '' : $sGlue, empty($sSuffix)? '' : $sSuffix );
	}
	
	/**
	 * @param string $insExistingListKey
	 * @param string $insFilterName
	 * @return array|false
	 */
	protected function processIpFilter( $insExistingListKey, $insFilterName ) {
		$aFilterIps = apply_filters( $insFilterName, array() );
		if ( empty( $aFilterIps ) ) {
			return false;
		}
			
		$aNewIps = array();
		foreach( $aFilterIps as $mKey => $sValue ) {
			if ( is_string( $mKey ) ) { //it's the IP
				$sIP = $mKey;
				$sLabel = $sValue;
			}
			else { //it's not an associative array, so the value is the IP
				$sIP = $sValue;
				$sLabel = '';
			}
			$aNewIps[ $sIP ] = $sLabel;
		}
		
		// now add and store the new IPs
		$aExistingIpList = $this->getOpt( $insExistingListKey );
		if ( !is_array( $aExistingIpList ) ) {
			$aExistingIpList = array();
		}

		$this->loadDataProcessor();
		$nNewAddedCount = 0;
		$aNewList = ICWP_WPSF_DataProcessor::Add_New_Raw_Ips( $aExistingIpList, $aNewIps, $nNewAddedCount );
		if ( $nNewAddedCount > 0 ) {
			$this->setOpt( $insExistingListKey, $aNewList );
		}
	}
	
	protected function loadDataProcessor() {
		if ( !class_exists('ICWP_WPSF_DataProcessor') ) {
			require_once( dirname(__FILE__).'/icwp-data-processor.php' );
		}
	}

	/**
	 * @return ICWP_WpFunctions_WPSF
	 */
	protected function loadWpFunctions() {
		return ICWP_WpFunctions_WPSF::GetInstance();
	}

	/**
	 * @return ICWP_WpFilesystem_WPSF
	 */
	protected function loadFileSystemProcessor() {
		if ( !class_exists('ICWP_WpFilesystem_WPSF') ) {
			require_once( dirname(__FILE__) . '/icwp-wpfilesystem.php' );
		}
		return ICWP_WpFilesystem_WPSF::GetInstance();
	}
}

endif;

class ICWP_OptionsHandler_Base_Wpsf extends ICWP_OptionsHandler_Base_V2 { }
