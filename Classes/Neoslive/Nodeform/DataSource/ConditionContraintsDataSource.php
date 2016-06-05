<?php
namespace Neoslive\Nodeform\DataSource;

use TYPO3\Neos\Service\DataSource\AbstractDataSource;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\Eel\FlowQuery\FlowQuery;

class ConditionContraintsDataSource extends AbstractDataSource {


    /**
     * @var string
     */
    static protected $identifier = 'neoslive-form-conditioncontraints';




    /**
     * Get data
     *
     * @param NodeInterface $node The node that is currently edited (optional)
     * @param array $arguments Additional arguments (key / value)
     * @return array JSON serializable data
     */
    public function getData(NodeInterface $node = NULL, array $arguments)
    {


        $operands = array();

        // find parent until form node
        if ($node->getNodeType()->getName() === 'Neoslive.Nodeform:Form') {
            $basenode = $node;
        } else {
            $basenode = null;
            $parent = $node->getParent();
            while ($parent) {
                $parent = $parent->getParent();
                if ($parent->getNodeType()->getName() == 'Neoslive.Nodeform:Form') {
                    $basenode = $parent;
                    $parent = false;
                }
            }
        }


        $flowQuery = new FlowQuery(array($basenode));
        $formElementNodes = $flowQuery->find("[instanceof Neoslive.Nodeform:AppFormElementsCompareableMixin]");


        // prepare and sort form elements by property text
        $formElementNodesSorted = array();
        foreach ($formElementNodes as $i => $n) {
            if ($n->getNodetype()->getName() != 'Neoslive.Nodeform:FormButton')
            {
                $formElementNodesSorted[$n->getNodeData()->getProperty('text')][] = $n;
            }

        }
        ksort($formElementNodesSorted);
        foreach ($formElementNodesSorted as $key => $val) {
            foreach ($val as $k => $v) {
                $formElementNodesSortedReduced[] = $v;
            }
        }


        // proceed form elements
        if (isset($formElementNodesSortedReduced)) {
            foreach ($formElementNodesSortedReduced as $i => $n) {


                $sectionlabel = '';
                $parent = $n->getParent();

                while ($parent) {

                    if ($parent->getNodeType()->getName() == 'Neoslive.Nodeform:Form' || $parent->getNodeType()->getName() == 'Neoslive.Nodeform:FinisherForm') {
                        $sectionlabel = $parent->getProperty('text');
                        $parent = false;
                    } else {
                        $parent = $parent->getParent();
                    }


                }

                if ($n->getNodeType()->getConfiguration('ui') && array_key_exists('icon', $n->getNodeType()->getConfiguration('ui'))) {
                    $icon = $n->getNodeType()->getConfiguration('ui')['icon'];
                } else {
                    $icon = '';
                }

                if ($sectionlabel == '') $sectionlabel = 'default';
                $operands[$sectionlabel][(string)$n->getIdentifier()] = array('value' => (string)$n->getIdentifier(), 'label' => $n->getNodeData()->getProperty('text'), 'group' => $sectionlabel, 'icon' => $icon);


            }
        }

        if (isset($operands) == false) {
            return array();
        }

        // TODO refactoring, see https://jira.neos.io/browse/NEOS-1476
        $operandsfinal = array();
        foreach ($operands as $key => $val) {
            foreach ($val as $id => $v) {
            $operandsfinal[(string)$id] = $v;
            }
        }



        return $operandsfinal;
    }




}