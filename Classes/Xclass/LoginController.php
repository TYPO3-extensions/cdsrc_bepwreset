<?php

namespace CDSRC\CdsrcBepwreset\Xclass;

/**
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Html\HtmlParser;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use CDSRC\CdsrcBepwreset\Utility\ExtensionConfigurationUtility;
use CDSRC\CdsrcBepwreset\Utility\HashUtility;
use CDSRC\CdsrcBepwreset\Utility\SessionUtility;

/**
 * Script Class for extending the login form rendering 
 *
 * @author Matthias Toscanelli <m.toscanelli@code-source.ch>
 */
class LoginController extends \TYPO3\CMS\Backend\Controller\LoginController {

    const RESULT_NONE = 0;
    const RESULT_OK = 1;
    const RESULT_ERROR = 2;

    /**
     * Current command
     * 
     * @var string
     */
    protected $command = '';

    /**
     * Previous command
     * 
     * @var string
     */
    protected $previousCommand = '';

    /**
     * Current username
     * 
     * @var string
     */
    protected $username = '';

    /**
     * Current reset code
     * 
     * @var string
     */
    protected $code = '';

    /**
     * Call result
     * 
     * @var integer
     */
    protected $result = self::RESULT_NONE;

    /**
     * Call result header
     * 
     * @var string
     */
    protected $header = '';

    /**
     * Call result message
     * 
     * @var string
     */
    protected $message = '';

    /**
     * Reset flash message if needed
     * 
     * @var \TYPO3\CMS\Core\Messaging\FlashMessage
     */
    protected $resetFlashMessage;

    /**
     * Initialize the login box. Will also react on a &L=OUT flag and exit.
     *
     * @return void
     * @todo Define visibility
     */
    public function init() {
        session_start();
        $GLOBALS['LANG']->includeLLFile('EXT:cdsrc_bepwreset/Resources/Private/Language/locallang.xlf');
        $this->initializeFromRequestOrSession();
        if (strlen($this->command) > 0) {
            $this->process();
            $GLOBALS['BE_USER']->logoff();
        }
        parent::init();
    }

    /**
     * Creates the login form
     * This is drawn when NO login exists.
     *
     * @return string HTML output
     */
    public function makeLoginForm() {
        $markers = array();
        if ($this->command === 'change' || $this->command === 'force') {
            $GLOBALS['TBE_TEMPLATE']->moduleTemplate = $GLOBALS['TBE_TEMPLATE']->getHtmlTemplate('EXT:cdsrc_bepwreset/Resources/Private/Templates/reset.html');
        }
        return HtmlParser::substituteMarkerArray(parent::makeLoginForm(), $markers, '###|###');
    }

    /**
     * Main function - creating the login/logout form
     *
     * @return void
     */
    public function main() {
        $pageRenderer = $GLOBALS['TBE_TEMPLATE']->getPageRenderer();
        $pageRenderer->addCssFile('../' . \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::siteRelPath('cdsrc_bepwreset') . 'Resources/Public/Css/login.css', 'stylesheet', 'all', '', FALSE);
        parent::main();
    }

    /**
     * Returns the form tag
     *
     * @return string Opening form tag string
     */
    public function startForm() {
        if ($this->command === 'change' || $this->command === 'force') {
            $output = '<form action="index.php" method="post" name="loginform">';
            SessionUtility::setDatas('reset', $this->username, $this->code);
            return $output;
        } else {
            return parent::startForm();
        }
    }

    /**
     * Wrapping the login form table in another set of tables etc:
     *
     * @param string $content HTML content for the login form
     * @return string The HTML for the page.
     * @todo Define visibility
     */
    public function wrapLoginForm($content) {

        $parentContent = parent::wrapLoginForm($content);

        $resetMessage = '';
        if ($this->result !== self::RESULT_NONE) {
            $resetMessage .= GeneralUtility::makeInstance('\\TYPO3\\CMS\\Core\\Messaging\\FlashMessage', $this->message, $this->header, $this->result === self::RESULT_ERROR ? FlashMessage::ERROR : FlashMessage::OK, TRUE)->render();
        }
        if ($this->command === 'force') {
            $resetMessage .= GeneralUtility::makeInstance('\\TYPO3\\CMS\\Core\\Messaging\\FlashMessage', '', $GLOBALS['LANG']->getLL('labels.changePasswordAtFirstLogin', TRUE), FlashMessage::INFO, TRUE)->render();
            $resetMessage .= $this->getBeSecurePwNotice();
        }
        if ($this->command === 'change') {
            $resetMessage .= $this->getBeSecurePwNotice();
        }

        $markers = array(
            'HEADLINE_RESET' => $GLOBALS['LANG']->getLL('headline.reset', TRUE),
            'RESET_MESSAGE' => $resetMessage,
            'VALUE_RESET' => $GLOBALS['LANG']->getLL('labels.reset', TRUE),
            'VALUE_RESETPW' => $GLOBALS['LANG']->getLL('labels.resetpw', TRUE),
            'LABEL_BACKTOLOGIN' => $GLOBALS['LANG']->getLL('labels.backToLogin', TRUE),
            'LABEL_SWITCHRESET' => $GLOBALS['LANG']->getLL('labels.switchToReset', TRUE),
            'LABEL_PASSWORD_CONFIRMATION' => $GLOBALS['LANG']->getLL('labels.password_confirmation', TRUE),
        );
        if ($this->isResetInProgress()) {
            $parentContent = HtmlParser::substituteSubpart($parentContent, '###LOGIN_ERROR###', '');
        }

        return HtmlParser::substituteMarkerArray($parentContent, $markers, '###|###');
    }

    /**
     * Initialize call parameter from request or session
     * 
     */
    protected function initializeFromRequestOrSession() {
        $command = (string) GeneralUtility::_GP('commandRS');
        if ($command === 'change') {
            $user = HashUtility::getUser(GeneralUtility::_GP('hash'));
            if($user === FALSE){
                $user = array('username' => '', 'tx_cdsrcbepwreset_resetHash' => '');
            }
            SessionUtility::setDatasAndRedirect($command, $user['username'], $user['tx_cdsrcbepwreset_resetHash']);
        } elseif ($command === 'send') {
            SessionUtility::setDatasAndRedirect($command, trim(GeneralUtility::_GP('username')));
        } else {
            $sessionParameters = SessionUtility::getDatas();
            if (is_array($sessionParameters)) {
                $this->command = (string) $sessionParameters['command'];
                $this->username = (string) $sessionParameters['username'];
                $this->code = (string) $sessionParameters['code'];
                $this->result = intval($sessionParameters['result']);
                $this->header = (string) $sessionParameters['header'];
                $this->message = (string) $sessionParameters['message'];
                $this->previousCommand = (string) $sessionParameters['previous'];
            }
            // Make sure that "Back to login form" work
            if ($this->command === 'reset' && strlen($command) === 0) {
                $this->command = '';
            }
            SessionUtility::reset();
        }
    }

    /**
     * Process reset command
     * 
     */
    protected function process() {
        if ($this->isResetInProgress() && is_object($GLOBALS['BE_USER']) && empty($GLOBALS['BE_USER']->user['uid'])) {
            $resetTool = GeneralUtility::makeInstance('CDSRC\\CdsrcBepwreset\\Tool\\ResetTool');
            try {
                if ($this->command === 'change' || $this->command === 'force') {
                    if (strlen($this->username) === 0 || !$resetTool->isCodeValidForUser($this->username, $this->code)) {
                        SessionUtility::setDatasAndRedirect('', '', '', self::RESULT_ERROR, $GLOBALS['LANG']->getLL('warning.resetPassword'), $GLOBALS['LANG']->getLL('warning.resetPassword.invalidResetCode'));
                    }
                } elseif ($this->command === 'reset') {
                    $resetTool->resetPassword($this->username, GeneralUtility::_GP('r_password'), GeneralUtility::_GP('r_password_confirmation'), $this->code);
                    SessionUtility::setDatasAndRedirect('', '', '', self::RESULT_OK, $GLOBALS['LANG']->getLL('ok.resetPassword'), $GLOBALS['LANG']->getLL('ok.resetPasswordMessage'));
                } elseif ($this->command === 'send' && ExtensionConfigurationUtility::isResetPasswordFromLoginFormEnable()) {
                    $resetTool->sendResetCode($this->username);
                    SessionUtility::setDatasAndRedirect('', '', '', self::RESULT_OK, $GLOBALS['LANG']->getLL('ok.sendCode'), $GLOBALS['LANG']->getLL('ok.sendCodeMessage'));
                } else {
                    // Unknown command
                    HttpUtility::redirect('index.php');
                    exit;
                }
            } catch (\Exception $e) {
                if($this->command === $this->previousCommand){
                    $command = '';
                }elseif($this->command === 'reset'){
                    $command = 'change';
                }elseif($this->command === 'change'){
                    $command = '';
                }else{
                    $command = $this->command;
                }
                SessionUtility::setDatasAndRedirect($command, $this->username, $this->code, self::RESULT_ERROR, $GLOBALS['LANG']->getLL('warning.resetPassword'), $this->catchResetToolException($e), $this->command);
            }
        }
    }

    /**
     * Checks if reset credentials are currently submitted
     *
     * @return boolean
     */
    protected function isResetInProgress() {
        return strlen($this->command) > 0;
    }

    /**
     * Get notice from be_secure_pw extension
     * @return string
     */
    protected function getBeSecurePwNotice() {
        if (ExtensionManagementUtility::isLoaded('be_secure_pw')) {
            // get configuration of a secure password
            $extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['be_secure_pw']);

            // how many parameters have to be checked
            $toCheckParams = array(
                'lowercaseChar',
                'capitalChar',
                'digit',
                'specialChar'
            );
            $checkParameter = array();
            foreach ($toCheckParams as $parameter) {
                if ($extConf[$parameter] == 1) {
                    $checkParameter[] = $GLOBALS['LANG']->sL('LLL:EXT:be_secure_pw/Resources/Private/Language/locallang.xml:' . $parameter);
                }
            }
            $flashMessage = GeneralUtility::makeInstance('\\TYPO3\\CMS\\Core\\Messaging\\FlashMessage', sprintf(
                                    '<small>' . $GLOBALS['LANG']->sL('LLL:EXT:be_secure_pw/Resources/Private/Language/locallang.xml:beSecurePw.description') . '</small>', $extConf['passwordLength'], implode(', ', $checkParameter), $extConf['patterns']
                            ), $GLOBALS['LANG']->sL('LLL:EXT:be_secure_pw/Resources/Private/Language/locallang.xml:beSecurePw.header'), FlashMessage::INFO, TRUE
            );
            return $flashMessage->render();
        }
        return '';
    }

    /**
     * Catch exception from ResetTool and return a flash message
     * 
     * @param \Exception $e
     * @return \TYPO3\CMS\Core\Messaging\FlashMessage
     * 
     */
    protected function catchResetToolException(\Exception $e) {
        if ($e instanceof \CDSRC\CdsrcBepwreset\Tool\Exception\InvalidUsernameException) {
            $error = $GLOBALS['LANG']->getLL('warning.resetPassword.userDoNotExists');
        } elseif ($e instanceof \CDSRC\CdsrcBepwreset\Tool\Exception\InvalidBackendUserException) {
            $error = $GLOBALS['LANG']->getLL('warning.resetPassword.userDoNotExists');
        } elseif ($e instanceof \CDSRC\CdsrcBepwreset\Tool\Exception\PasswordResetPreventedForAdminException) {
            $error = $GLOBALS['LANG']->getLL('warning.resetPassword.passwordResetPreventedForAdmin');
        } elseif ($e instanceof \CDSRC\CdsrcBepwreset\Tool\Exception\UserInBlackListException) {
            $error = $GLOBALS['LANG']->getLL('warning.resetPassword.userIsInBlackList');
        } elseif ($e instanceof \CDSRC\CdsrcBepwreset\Tool\Exception\UserNotInWhiteListException) {
            $error = $GLOBALS['LANG']->getLL('warning.resetPassword.userIsNotInBlackList');
        } elseif ($e instanceof \CDSRC\CdsrcBepwreset\Tool\Exception\InvalidUserEmailException) {
            $error = $GLOBALS['LANG']->getLL('warning.resetPassword.invalidUserEmail');
        } elseif ($e instanceof \CDSRC\CdsrcBepwreset\Tool\Exception\UserHasNoEmailException) {
            $error = $GLOBALS['LANG']->getLL('warning.resetPassword.userHasNoEmail');
        } elseif ($e instanceof \CDSRC\CdsrcBepwreset\Tool\Exception\InvalidResetCodeException) {
            $error = $GLOBALS['LANG']->getLL('warning.resetPassword.invalidResetCode');
        } elseif ($e instanceof \CDSRC\CdsrcBepwreset\Tool\Exception\InvalidPasswordConfirmationException) {
            $error = $GLOBALS['LANG']->getLL('warning.resetPassword.invalidPasswordConfirmation');
        } elseif ($e instanceof \CDSRC\CdsrcBepwreset\Tool\Exception\EmptyPasswordException) {
            $error = $GLOBALS['LANG']->getLL('warning.resetPassword.emptyPassword');
        } elseif ($e instanceof \CDSRC\CdsrcBepwreset\Tool\Exception\BeSecurePwException) {
            $error = $GLOBALS['LANG']->getLL('warning.resetPassword.beSecurePw');
        } elseif ($e instanceof \CDSRC\CdsrcBepwreset\Tool\Exception\EmailNotSentException) {
            $error = $GLOBALS['LANG']->getLL('warning.resetPassword.emailNotSent');
        } else {
            $error = $GLOBALS['LANG']->getLL('warning.resetPassword.unknown');
        }
        // Make call slower to prevent multiple fast call
        sleep(5);
        return $error;
    }

}
