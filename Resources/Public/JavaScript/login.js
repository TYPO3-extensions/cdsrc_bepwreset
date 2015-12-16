var TYPO3BackendLogin_switchToOpenId_backup = TYPO3BackendLogin.switchToOpenId;
var TYPO3BackendLogin_switchToDefault_backup = TYPO3BackendLogin.switchToDefault;
var TYPO3BackendLogin_registerEventListeners_backup = TYPO3BackendLogin.registerEventListeners;
var TYPO3BackendLogin_showLoginProcess_backup = TYPO3BackendLogin.showLoginProcess;

TYPO3BackendLogin.switchToOpenId = function () {
    TYPO3BackendLogin_switchToOpenId_backup.call();
    $('t3-login-form-footer-reset').hide();
    $('t3-login-form-footer-reset-default').hide();
    $('t3-login-submit').show().enable();
    $('t3-login-reset').hide().disable();
    Ext.query('input[name="login_status"]')[0].setValue('login');
};
TYPO3BackendLogin.switchToDefault = function () {
    TYPO3BackendLogin_switchToDefault_backup.call();
    $('t3-login-form-footer-reset').show();
    $('t3-login-form-footer-reset-default').hide();
    $('t3-login-submit').show().enable();
    $('t3-login-reset').hide().disable();
    Ext.query('input[name="login_status"]')[0].setValue('login');
};
TYPO3BackendLogin.switchToReset = function () {
    $('t3-login-openIdLogo').hide();

    if ($('t3-username').getValue() == 'openid_url') {
        $('t3-username').setValue('');
    }
    $('t3-password').setValue('');

    $('t3-login-form-footer-default').hide();
    $('t3-login-form-footer-reset').hide();
    $('t3-login-form-footer-reset-default').show();
    $('t3-login-form-footer-openId').hide();

    $('t3-login-username-section').show();
    $('t3-login-password-section').hide();
    $('t3-login-openid_url-section').hide();

    if ($('t3-login-interface-section')) {
        $('t3-login-interface-section').hide();
    }

    $('t3-username').activate();

    $('t3-login-submit').hide().disable();
    $('t3-login-reset').show().enable();
    Ext.query('input[name="login_status"]')[0].setValue('reset');
};
TYPO3BackendLogin.showLoginProcess = function () {
    TYPO3BackendLogin_showLoginProcess_backup.call();
    if ($('t3-login-reset-message')) {
        $('t3-login-reset-message').hide();
    }
};
TYPO3BackendLogin.setResetCommand = function () {
    if ($('t3-login-reset-value')) {
        $('t3-login-reset-value').setValue('send');
    }
}
TYPO3BackendLogin.registerEventListeners = function () {
    TYPO3BackendLogin_registerEventListeners_backup.call();
    Event.observe(
            $('t3-login-switchToResetPw'),
            'click',
            TYPO3BackendLogin.switchToReset
            );
    Event.observe(
            $('t3-login-switchToDefaultFromResetPw'),
            'click',
            TYPO3BackendLogin.switchToDefault
            );
    Event.observe(
            $('t3-login-reset'),
            'click',
            TYPO3BackendLogin.setResetCommand
            );
};
