<?php
/**
 * @category   AvS
 * @package    AvS_ScopeHint
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author     Andreas von Studnitz <avs@avs-webentwicklung.de>
 */

/**
 * Render config field; hint added when config value is overwritten in a scope below
 */
class AvS_ScopeHint_Block_AdminhtmlSystemConfigFormField
    extends Mage_Adminhtml_Block_System_Config_Form_Field
    implements Varien_Data_Form_Element_Renderer_Interface
{
    /**
     * Renders a config field; scope hint added
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $id = $element->getHtmlId();

        $useContainerId = $element->getData('use_container_id');
        $html = '<tr id="row_' . $id . '">'
                . '<td class="label"><label for="' . $id . '">' . $element->getLabel() . '</label></td>';

        //$isDefault = !$this->getRequest()->getParam('website') && !$this->getRequest()->getParam('store');
        $isMultiple = $element->getExtType() === 'multiple';

        // replace [value] with [inherit]
        $namePrefix = preg_replace('#\[value\](\[\])?$#', '', $element->getName());

        $options = $element->getValues();

        $addInheritCheckbox = false;
        if ($element->getCanUseWebsiteValue()) {
            $addInheritCheckbox = true;
            $checkboxLabel = Mage::helper('adminhtml')->__('Use Website');
        }
        elseif ($element->getCanUseDefaultValue()) {
            $addInheritCheckbox = true;
            $checkboxLabel = Mage::helper('adminhtml')->__('Use Default');
        }

        if ($addInheritCheckbox) {
            $inherit = $element->getInherit() == 1 ? 'checked="checked"' : '';
            if ($inherit) {
                $element->setDisabled(true);
            }
        }

        if ($element->getTooltip()) {
            $html .= '<td class="value with-tooltip">';
            $html .= $this->_getElementHtml($element);
            $html .= '<div class="field-tooltip"><div>' . $element->getTooltip() . '</div></div>';
        } else {
            $html .= '<td class="value">';
            $html .= $this->_getElementHtml($element);
        };

        if ($element->getComment()) {
            $html .= '<p class="note"><span>' . $element->getComment() . '</span></p>';
        }
        $html .= '</td>';

        if ($addInheritCheckbox) {

            $defText = $element->getDefaultValue();
            if ($options) {
                $defTextArr = array();
                foreach ($options as $k => $v) {
                    if (!isset($v['value'])) {
                        continue;
                    }
                    if ($isMultiple) {
                        if (is_array($v['value']) && in_array($k, $v['value'])) {
                            $defTextArr[] = $v['label'];
                        }
                    } elseif (isset($v['value']) && $v['value'] == $defText) {
                        $defTextArr[] = $v['label'];
                        break;
                    }
                }
                $defText = join(', ', $defTextArr);
            }

            // default value
            $html .= '<td class="use-default">';
            //$html.= '<input id="'.$id.'_inherit" name="'.$namePrefix.'[inherit]" type="checkbox" value="1" class="input-checkbox config-inherit" '.$inherit.' onclick="$(\''.$id.'\').disabled = this.checked">';
            $html .= '<input id="' . $id . '_inherit" name="' . $namePrefix . '[inherit]" type="checkbox" value="1" class="checkbox config-inherit" ' . $inherit . ' onclick="toggleValueElements(this, Element.previous(this.parentNode))" /> ';
            $html .= '<label for="' . $id . '_inherit" class="inherit" title="' . htmlspecialchars($defText) . '">' . $checkboxLabel . '</label>';
            $html .= '</td>';
        }

        $html .= '<td class="scope-label">';
        if ($element->getScope()) {
            $html .= $element->getScopeLabel();
        }
        $html .= '<br />';
        $html .= $this->_getConfigCode($element);
        $html .= '</td>';

        $html .= '<td class="scopehint" style="padding: 6px 6px 0 6px;">';
        $html .= $this->_getScopeHintHtml($element);
        $html .= '</td>';

        $html .= '<td class="">';
        if ($element->getHint()) {
            $html .= '<div class="hint" >';
            $html .= '<div style="display: none;">' . $element->getHint() . '</div>';
            $html .= '</div>';
        }
        $html .= '</td>';

        $html .= '</tr>';
        return $html;
    }

    /**
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getScopeHintHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return $this->getLayout()
            ->createBlock('scopehint/hint', 'scopehint')
            ->setElement($element)
            ->setType('config')
            ->toHtml();
    }

    /**
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getConfigCode(Varien_Data_Form_Element_Abstract $element)
    {
        if (isset($element->field_config->config_path)) {
            return (string) $element->field_config->config_path;
        }

        $configCode = preg_replace('#\[value\](\[\])?$#', '', $element->getName());
        $configCode = str_replace('[fields]', '', $configCode);
        $configCode = str_replace('groups[', '[', $configCode);
        $configCode = str_replace('][', '/', $configCode);
        $configCode = str_replace(']', '', $configCode);
        $configCode = str_replace('[', '', $configCode);
        $group = substr($configCode, 0, strpos($configCode, '/'));
        $field = substr($configCode, strpos($configCode, '/') + 1);
        $configCode = Mage::app()->getRequest()->getParam('section') . '/' . $configCode;

        $configFields   = Mage::getSingleton('adminhtml/config');

        $section = Mage::app()->getRequest()->getParam('section');
        /** @var Mage_Core_Model_Config_Element $section */
        $section   = $configFields->getSection($section);

        $groupNode = $section->groups->$group;
        if (! $groupNode) {
            return $configCode;
        }
        $fieldNode = $groupNode->fields->$field;

        $helperClassName = $configFields->getAttributeModule($section, $groupNode, $fieldNode ?: null);
        $helper = Mage::helper($helperClassName);
        if (! $helper) {
            $moduleName = 'unknown';
        } else {
            $helperName = get_class($helper);
            $moduleName = substr(get_class($helper), 0, strpos($helperName, '_Helper'));
        }


        $rewrites = Mage::getConfig()->getNode()->xpath('//global/helpers//rewrite');
        $hasRewrite = false;
        $rewriteHelperClass = null;
        foreach ($rewrites as $rewrite) {
            /** @var Mage_Core_Model_Config_Element $rewrite */
            /** @var Mage_Core_Model_Config_Element $parentNode */
            $parentNode = $rewrite->getParent();
            if ($parentNode->getName() == $helperClassName) {
                $hasRewrite = true;
                if (isset($parentNode->getParent()->$helperClassName->class)) {
                    $rewriteHelperClass = $parentNode->getParent()->$helperClassName->class;
                }
            }
        }

        if ($hasRewrite && !$rewriteHelperClass) {
            $moduleName .= ' < '. 'Mage_'.ucfirst($helperClassName);
        } elseif($hasRewrite) {
            $moduleName .= ' < '. substr($rewriteHelperClass, 0, strpos($rewriteHelperClass, '_Helper'));
        }

        $configCode = $configCode."<br />\n". $moduleName .'';
        return $configCode;
    }

}
