<?php

if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}
if (TYPO3_MODE == 'BE' || TYPO3_MODE == 'FE' && isset($GLOBALS['BE_USER'])) {
    global $TBE_STYLES; 
    
    if (\CDSRC\CdsrcBepwreset\Utility\ExtensionConfigurationUtility::isResetPasswordFromLoginFormEnable()) {
        // Adding HTML template for login screen
        $TBE_STYLES['htmlTemplates']['EXT:backend/Resources/Private/Templates/login.html'] = 'EXT:cdsrc_bepwreset/Resources/Private/Templates/login.html';
    }

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['TYPO3\\CMS\\Backend\\Controller\\LoginController'] = array(
        'className' => 'CDSRC\\CdsrcBepwreset\\Xclass\\LoginController',
    );
}

