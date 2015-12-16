<?php

namespace CDSRC\CdsrcBepwreset\Utility;

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
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Backend\Utility\BackendUtility;

/**
 * class providing configuration checks for cdsrc_bepwreset.
 *
 * @author Matthias Toscanelli <m.toscanelli@code-source.ch>
 */
class ExtensionManagerConfigurationUtility {

    /**
     * Store javascript insertion status
     * 
     * @var boolean 
     */
    protected static $javascriptFunctionInserted = FALSE;
    
    /**
     * Store javascript id index
     * @var integer
     */
    protected static $javascriptIdIndex = 0;
    
    /**
     * @var array
     */
    protected $extConf = array();
    
    /**
     * Render a multiple select for backend groups
     * 
     * @param array $params
     * @return string
     */
    public function renderBackendGroupSelect($params) {
        $selectedGroups = $params['fieldValue'];
        if (!is_array($selectedGroups)) {
            $selectedGroups = explode(',', $selectedGroups);
        }
        $groups = BackendUtility::getRecordsByField('be_groups', 'deleted', 0, '', '', 'title ASC');
        
        $id = 'cdsrc_bepwreset_' . self::$javascriptIdIndex;
        self::$javascriptIdIndex++;
        $content = '<select id="'.$id.'" name="none['.$id.'][]" multiple="multiple" size="5" style="min-width:200px;">';
        if (is_array($groups)) {
            foreach ($groups as $group) {
                $content .= '<option value="' . $group['uid'] . '" ' . (in_array($group['uid'], $selectedGroups) ? 'selected="selected"' : '') . '>' . $group['title'] . '</option>';
            }
        }
        $content .= '</select>';
        $content .= '<input id="'.$id.'_input" type="hidden" value="'.implode(',', array_unique($selectedGroups)).'" name="'.$params['fieldName'].'" />';
        $content .= $this->loadJavascriptFunction();
        $content .= '<script type="text/javascript">
            CDSRC_CdsrcBepwreset.addEvent(\''.$id.'\', \'change\', CDSRC_CdsrcBepwreset.setSelectValue);
        </script>';
        return $content;
    }
    
    /**
     * Load javascript function once
     * 
     * @return string
     */
    protected function loadJavascriptFunction(){
        if(!self::$javascriptFunctionInserted){
            self::$javascriptFunctionInserted = TRUE;
            return '<script type="text/javascript">
                CDSRC_CdsrcBepwreset = {
                    events: [],
                    start: function(){
                        for(var i=0; i<this.events.length; i++){
                            if(this.events[i].element){
                               this.events[i].element.on(this.events[i].action, this.events[i].callback);
                            }
                        }
                    },
                    addEvent: function(id, action, callback){
                        this.events.push({
                            element: Ext.get(id),
                            action: action,
                            callback: callback
                        });
                    },
                    setSelectValue: function(){
                        var input = Ext.get(this.dom.id + \'_input\');
                        if(input){
                            var values = [];
                            for(i=0; i<this.dom.options.length; i++){
                                if (this.dom.options[i].selected){
                                    values.push(this.dom.options[i].value);
                                }
                            }
                            input.dom.value = values.join();
                        }
                    }
                };
                Ext.onReady(CDSRC_CdsrcBepwreset.start, CDSRC_CdsrcBepwreset);
            </script>';
        }
        return '';
    }

    /**
     * Processes the information submitted by the user using a POST request and
     * transforms it to a TypoScript node notation.
     *
     * @param array $postArray Incoming POST information
     * @return array Processed and transformed POST information
     */
    private function processPostData(array $postArray = array()) {
        foreach ($postArray as $key => $value) {
            $parts = explode('.', $key, 2);
            if (count($parts) == 2) {
                $value = $this->processPostData(array($parts[1] => $value));
                $postArray[$parts[0] . '.'] = array_merge((array) $postArray[($parts[0] . '.')], $value);
            } else {
                $postArray[$parts[0]] = $value;
            }
        }
        return $postArray;
    }

}
