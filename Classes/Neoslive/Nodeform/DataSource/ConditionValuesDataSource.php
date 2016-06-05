<?php
namespace Neoslive\Nodeform\DataSource;

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Neos\Service\DataSource\AbstractDataSource;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\Eel\FlowQuery\FlowQuery;
use TYPO3\TYPO3CR\Domain\Repository\NodeDataRepository;

class ConditionValuesDataSource extends AbstractDataSource {


    /**
     * @var string
     */
    static protected $identifier = 'neoslive-form-conditionvalues';

    /**
     * @Flow\Inject
     * @var NodeDataRepository
     */
    protected $nodeDataRepository;


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

        $basenode = null;

        // find parent until form node
        $basenodedata = $this->nodeDataRepository->findOneByIdentifier($node->getNodeData()->getProperty('operand'), $node->getContext()->getWorkspace());
        if ($basenodedata) $basenode = new \TYPO3\TYPO3CR\Domain\Model\Node($basenodedata,$node->getContext());


        if ($basenode) {
            $flowQuery = new FlowQuery(array($basenode));
            $formElementNodes = $flowQuery->find("[instanceof Neoslive.Nodeform:FormSelectOption]");


            foreach ($formElementNodes as $i => $n) {

                $sectionlabel = '';
                $parent = $n->getParent();
                while ($parent) {

                    if ($parent->getNodeType()->getName() == 'Neoslive.Nodeform:FormSelect') {
                        $sectionlabel = $parent->getNodeData()->getProperty('text');
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

                $operands[$sectionlabel][$n->getIdentifier()] = array('label' => $n->getNodeData()->getProperty('text'), 'group' => $sectionlabel, 'icon' => $icon);

            }

        }


        if (count($operands) == 0) {
            $operands['boolean']['1'] = array('value'=>'1','label' => 'true', 'group' => '', 'icon' => 'icon-ok');
            $operands['boolean']['0'] = array('value'=>'0', 'label' => 'false', 'group' => '', 'icon' => 'icon-minus-sign');
        }




        // TODO refactoring, see https://jira.neos.io/browse/NEOS-1476
        $operandsfinal = array();
        foreach ($operands as $key => $val) {
            foreach ($val as $id => $v) {
            $operandsfinal[$id] = $v;
            }
        }





        return $operandsfinal;
    }




}