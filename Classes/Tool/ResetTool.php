<?php

namespace CDSRC\CdsrcBepwreset\Tool;

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
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use CDSRC\CdsrcBepwreset\Utility\ExtensionConfigurationUtility;
use CDSRC\CdsrcBepwreset\Utility\HashUtility;
use CDSRC\CdsrcBepwreset\Utility\LogUtility;

/**
 * 
 *
 * @author Matthias Toscanelli <m.toscanelli@code-source.ch>
 */
class ResetTool {

    /**
     * Extention key
     * 
     * @var string
     */
    protected $extKey = 'cdsrc_bepwreset';

    /**
     * Backend user datas
     * 
     * @var array
     */
    protected $user;

    /**
     * Send a new reset code to user by email
     * 
     * @param string $username
     * 
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\EmailNotSentException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\InvalidBackendUserException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\InvalidUserEmailException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\InvalidUsernameException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\PasswordResetPreventedForAdminException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\ResetCodeNotUpdatedException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\UserHasNoEmailException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\UserInBlackListException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\UserNotInWhiteListException
     */
    public function sendResetCode($username) {
        // This call disable bypassedOnResetAtNextLogin
        $this->initUser($username, FALSE);
        $GLOBALS['LANG']->includeLLFile('EXT:cdsrc_bepwreset/Resources/Private/Language/locallang.xlf');

        if (($fields = $this->updateResetCode()) !== FALSE) {
            $markers = array(
                'USERNAME' => $this->user['username'],
                'VALIDITY' => BackendUtility::datetime($fields['tx_cdsrcbepwreset_resetHashValidity']),
                'LINK' => GeneralUtility::getIndpEnv('TYPO3_REQUEST_HOST') . '/typo3/index.php?commandRS=change&hash='.HashUtility::getHash($this->user['username'], $fields['tx_cdsrcbepwreset_resetHash']),
                'SITENAME' => $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename']
            );

            $subject = HtmlParser::substituteMarkerArray($GLOBALS['LANG']->getLL('sendResetcode.subject'), $markers, '###|###');
            $msg = HtmlParser::substituteMarkerArray($GLOBALS['LANG']->getLL('sendResetcode.message'), $markers, '###|###');
            $from = \TYPO3\CMS\Core\Utility\MailUtility::getSystemFrom();

            /** @var $mail \TYPO3\CMS\Core\Mail\MailMessage */
            $mail = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Mail\\MailMessage');
            $mail->setTo($this->user['email'])->setFrom($from)->setSubject($subject)->setBody($msg);
            $mail->send();
            if (!$mail->isSent()) {
                throw new \CDSRC\CdsrcBepwreset\Tool\Exception\EmailNotSentException('Email not sent.', 1424721934);
            }
        } else {
            throw new \CDSRC\CdsrcBepwreset\Tool\Exception\ResetCodeNotUpdatedException('Enable to append reset code to user.', 1424785971);
        }
    }

    /**
     * Set a new reset code to user and return datas
     * 
     * @param string $username
     * @return mixed Array of updated fields or FALSE if error happens
     * 
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\InvalidBackendUserException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\InvalidUsernameException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\InvalidUserEmailException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\ResetCodeNotUpdatedException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\PasswordResetPreventedForAdminException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\UserHasNoEmailException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\UserInBlackListException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\UserNotInWhiteListException
     */
    public function updateResetCodeForUser($username){
        $this->initUser($username, ExtensionConfigurationUtility::checkAreBypassedOnResetAtNextLogin());
        if (($fields = $this->updateResetCode()) !== FALSE) {
            return $fields;
        } else {
            throw new \CDSRC\CdsrcBepwreset\Tool\Exception\ResetCodeNotUpdatedException('Enable to append reset code to user.', 1424785971);
        }
    }

    /**
     * Reset password for backend user
     * 
     * @param string $username
     * @param string $password
     * @param string $passwordConfirmation
     * @param string $code
     * 
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\BackendUserNotInitializedException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\BeSecurePwException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\EmptyPasswordException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\InvalidBackendUserException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\InvalidPasswordConfirmationException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\InvalidResetCodeException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\InvalidUsernameException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\InvalidUserEmailException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\PasswordResetPreventedForAdminException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\UserHasNoEmailException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\UserInBlackListException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\UserNotInWhiteListException
     */
    public function resetPassword($username, $password, $passwordConfirmation, $code) {
        $this->initUser($username, ExtensionConfigurationUtility::checkAreBypassedOnResetAtNextLogin());
        if ($this->isValidResetCode($code)) {
            $trimedPassword = trim($password);
            if (strlen($trimedPassword) > 0) {
                if ($trimedPassword === trim($passwordConfirmation)) {
                    if (ExtensionManagementUtility::isLoaded('be_secure_pw')) {
                        $set = TRUE;
                        $is_in = '';
                        $eval = GeneralUtility::makeInstance('SpoonerWeb\BeSecurePw\Evaluation\PasswordEvaluator');
                        $check = $eval->evaluateFieldValue($trimedPassword, $is_in, $set);
                        if (strlen($check) === 0) {
                            throw new \CDSRC\CdsrcBepwreset\Tool\Exception\BeSecurePwException('Password is not enough strong.', 1424736449);
                        }
                    }
                    if (is_object($GLOBALS['BE_USER'])) {
                        $storeRec = array(
                            'be_users' => array(
                                $this->user['uid'] => array(
                                    'password' => $trimedPassword,
                                    'tx_cdsrcbepwreset_resetHash' => '',
                                    'tx_cdsrcbepwreset_resetHashValidity' => 0,
                                    'tx_cdsrcbepwreset_resetAtNextLogin' => 0
                                )
                            )
                        );
                        // Make instance of TCE for storing the changes.
                        $tce = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\DataHandling\\DataHandler');
                        $tce->stripslashes_values = 0;
                        $tce->start($storeRec, array(), $GLOBALS['BE_USER']);
                        // This is so the user can actually update his user record.
                        $tce->admin = 1;
                        // Desactivate history
                        $tce->checkSimilar = FALSE;
                        // This is to make sure that the users record can be updated even if in another workspace. This is tolerated.
                        $tce->bypassWorkspaceRestrictions = TRUE;
                        $tce->process_datamap();
                        unset($tce);
                        LogUtility::writeLog('Password has been reset for "%s (%s)" from %s', $this->user['uid'], $this->user['username'], $this->user['uid'], (string) GeneralUtility::getIndpEnv('REMOTE_ADDR'));
                    } else {
                        throw new \CDSRC\CdsrcBepwreset\Tool\Exception\BackendUserNotInitializedException('Backend user object is not initialized.', 1424720202);
                    }
                } else {
                    throw new \CDSRC\CdsrcBepwreset\Tool\Exception\InvalidPasswordConfirmationException('Confirmation password is not valid.', 1424718822);
                }
            } else {
                throw new \CDSRC\CdsrcBepwreset\Tool\Exception\EmptyPasswordException('Password is empty.', 1424718754);
            }
        } else {
            throw new \CDSRC\CdsrcBepwreset\Tool\Exception\InvalidResetCodeException('"' . $code . '" is not valid.', 1424710407);
        }
    }

    /**
     * Static function to check if code is valid for an user
     * 
     * @param string $username
     * @param string $code
     * @return boolean
     * 
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\InvalidBackendUserException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\InvalidUsernameException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\InvalidUserEmailException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\PasswordResetPreventedForAdminException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\UserHasNoEmailException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\UserInBlackListException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\UserNotInWhiteListException
     */
    public function isCodeValidForUser($username, $code) {
        $this->initUser($username, ExtensionConfigurationUtility::checkAreBypassedOnResetAtNextLogin());
        return $this->isValidResetCode($code);
    }

    /**
     * Add a new reset code to current user
     * 
     * @return mixed Array of updated fields or FALSE if error happens
     */
    protected function updateResetCode() {
        if (!empty($this->user)) {
            $fields = array(
                'tstamp' => $GLOBALS['EXEC_TIME'],
                'tx_cdsrcbepwreset_resetHash' => md5($GLOBALS['EXEC_TIME'] . '-' . mt_rand(1000, 100000)),
                'tx_cdsrcbepwreset_resetHashValidity' => $GLOBALS['EXEC_TIME'] + 3600
            );

            if ($GLOBALS['TYPO3_DB']->exec_UPDATEquery('be_users', 'uid=' . intval($this->user['uid']), $fields)) {
                return $fields;
            }
        }
        return FALSE;
    }

    /**
     * Initialize User
     * 
     * @param string $username
     * 
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\InvalidBackendUserException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\InvalidUsernameException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\InvalidUserEmailException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\PasswordResetPreventedForAdminException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\UserHasNoEmailException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\UserInBlackListException
     * @throws \CDSRC\CdsrcBepwreset\Tool\Exception\UserNotInWhiteListException
     */
    protected function initUser($username, $bypassCheckOnResetAtNextLogin=TRUE) {
        $username = trim($username);
        if (strlen($username) === 0) {
            throw new \CDSRC\CdsrcBepwreset\Tool\Exception\InvalidUsernameException('Username is empty.', 1424708826);
        }
        $users = BackendUtility::getRecordsByField('be_users', 'username', $username);
        $count = count($users);
        if ($count === 1) {
            $this->user = $users[0];
        } elseif ($count === 0) {
            throw new \CDSRC\CdsrcBepwreset\Tool\Exception\InvalidBackendUserException('User do not exists.', 1424709938);
        } else {
            throw new \CDSRC\CdsrcBepwreset\Tool\Exception\InvalidBackendUserException('Multiple record found.', 1424709961);
        }
        
        // Administrator, white list and black list are not checked if user require a password reset at next login
        if(intval($this->user['tx_cdsrcbepwreset_resetAtNextLogin']) === 0 || !$bypassCheckOnResetAtNextLogin){
            if($this->user['admin'] && !ExtensionConfigurationUtility::isAdminAllowedToResetPassword()){
                throw new \CDSRC\CdsrcBepwreset\Tool\Exception\PasswordResetPreventedForAdminException('Admin is not allowed to reset password.', 1424814441);
            }

            if(!ExtensionConfigurationUtility::isUserInWhiteList($this->user)){
                throw new \CDSRC\CdsrcBepwreset\Tool\Exception\UserNotInWhiteListException('White list is configured and user is not in.', 1424825158);
            }

            if(ExtensionConfigurationUtility::isUserInBlackList($this->user)){
                throw new \CDSRC\CdsrcBepwreset\Tool\Exception\UserInBlackListException('Black list is configured and user is in.', 1424825189);
            }
        }

        if (strlen(trim($this->user['email'])) === 0) {
            throw new \CDSRC\CdsrcBepwreset\Tool\Exception\UserHasNoEmailException('"' . $this->user['username'] . '" has no email defined.', 1424708950);
        } elseif (!GeneralUtility::validEmail($this->user['email'])) {
            throw new \CDSRC\CdsrcBepwreset\Tool\Exception\InvalidUserEmailException('"' . $this->user['username'] . '" has no valid email address.', 1424710072);
        }
    }

    /**
     * Check if given code is valid for backend user
     * 
     * @param string $code
     * @return boolean
     */
    protected function isValidResetCode($code) {
        if (!empty($this->user)) {
            return strlen($this->user['tx_cdsrcbepwreset_resetHash']) > 0 &&
                    $this->user['tx_cdsrcbepwreset_resetHash'] === $code &&
                    $this->user['tx_cdsrcbepwreset_resetHashValidity'] >= $GLOBALS['EXEC_TIME'];
        }
        return FALSE;
    }

}
