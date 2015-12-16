<?php

namespace CDSRC\CdsrcBepwreset\Hooks;

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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use CDSRC\CdsrcBepwreset\Utility\LogUtility;
use CDSRC\CdsrcBepwreset\Utility\SessionUtility;

/**
 * Hook user authentication to reset password if option is set.
 *
 * @author Matthias Toscanelli <m.toscanelli@code-source.ch>
 */
class UserAuthHook {
    
    /**
     * Log off and redirect if password reset is required
     * @param array $params
     * @param \TYPO3\CMS\Core\Authentication\BackendUserAuthentication $pObj
     */
    public function postUserLookUp($params, $pObj){
        if($pObj instanceof \TYPO3\CMS\Core\Authentication\BackendUserAuthentication){
            if(!empty($pObj->user)){
                if(intval($pObj->user['tx_cdsrcbepwreset_resetAtNextLogin']) === 1){
                    try{
                        $user = $pObj->user;
                        $resetTool = GeneralUtility::makeInstance('CDSRC\\CdsrcBepwreset\\Tool\\ResetTool');
                        $fields = $resetTool->updateResetCodeForUser($user['username']);
                        
                        $pObj->logoff();
                        
                        LogUtility::writeLog('Password change request generated for "%s (%s)"', $user['uid'], $user['username'], $user['uid']);
                        SessionUtility::setDatasAndRedirect('force', $user['username'], $fields['tx_cdsrcbepwreset_resetHash']);
                    }catch(\Exception $e){
                        // Do not log off if reset code could not been updated
                        LogUtility::writeLog('Unable to update password reset code for user "%s (%s)"', $pObj->user['uid'], $pObj->user['username'], $pObj->user['uid']);
                    }
                }
            }
        }
    }

}
