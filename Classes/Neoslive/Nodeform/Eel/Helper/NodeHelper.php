<?php

namespace Neoslive\Nodeform\Eel\Helper;

use TYPO3\Eel\FlowQuery\FlowQuery;
use TYPO3\Eel\ProtectedContextAwareInterface;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Http\Request;
use TYPO3\TYPO3CR\Domain\Factory\NodeFactory;
use TYPO3\TYPO3CR\Domain\Model\Node;
use TYPO3\TYPO3CR\Domain\Repository\NodeDataRepository;




class NodeHelper implements ProtectedContextAwareInterface
{


    /**
     * @Flow\Inject
     * @var NodeFactory
     */
    protected $nodeFactory;


    /**
     * @var Request
     */
    protected $httpRequest;

    /**
     * @var Request
     */
    protected $httpRequestData;

    /**
     * @var Request
     */
    protected $httpRequestDataAll;


    /**
     * @var array
     */
    private $conditionsCalculated;


    /**
     * @var array
     */
    private $conditionsCalculators;

    /**
     * @var array
     */
    private $conditionsCalculatorsNestedTemp;


    /**
     * @Flow\Inject
     * @var NodeDataRepository
     */
    protected $nodeDataRepository;


    /**
     * @var boolean
     */
    protected $isBackend;


    /**
     * @var array
     */
    protected $currentFormIds;




    /**
     * constructor.
     */
    public function __construct()
    {

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;

        $this->httpRequest = new Request($_GET, $input, $_FILES, $_SERVER);

        if (is_array($input) && array_key_exists('data',$input)) {
            $this->httpRequestData = new Request($_GET, $input['data'], $_FILES, $_SERVER);
        } else {
            $this->httpRequestData = $this->httpRequest;
        }

        if (is_array($input) && array_key_exists('dataAll',$input)) {
            $this->httpRequestDataAll = new Request($_GET, $input['dataAll'], $_FILES, $_SERVER);
        } else {
            $this->httpRequestDataAll = $this->httpRequest;
        }

        $this->conditionsCalculated = array();
        $this->conditionsCalculators = array();
        $this->currentFormIds = array();
        $this->conditionsCalculatorsNestedTemp = array();



    }



    /**
     * Register returned node
     *
     * @param string $nodeId
     * @return mixed
     */
    public function addRegisteredNodes($nodeId)
    {

        $_GET['NeosliveNodeForm-returnedNodes'][$nodeId] = true;


    }

    /**
     * de-register returned node
     *
     * @param string $nodeId
     * @return mixed
     */
    public function removeRegisteredNodes($nodeId)
    {

        if (isset($GLOBALS['NeosliveNodeForm-returnedNodes'][$nodeId])) unset($GLOBALS['NeosliveNodeForm-returnedNodes'][$nodeId]);

    }

    /**
     * Register returned node
     *
     * @return array
     */
    public function getRegisteredNodes()
    {

        return isset($_GET['NeosliveNodeForm-returnedNodes']) ? $_GET['NeosliveNodeForm-returnedNodes'] : array();

    }

    /**
     * Get nodes returned nodes hash
     *
     * @return mixed
     */
    public function getReturnedNodesHash()
    {

        return sha1(json_encode($this->getRegisteredNodes()));

    }



    /**
     * Get nodes linked with given linking condition node type
     *
     * @param Node $node
     * @param $linkedNodeType Name of linking node type
     * @return string
     */
    public function getElementsLinkedWithConditions(Node $node,$linkedNodeType)
    {

        $linkedWithConditionOperand = array();

        $flowQuery = new FlowQuery(array($node));
        $formElements = $flowQuery->find("[instanceof ".$linkedNodeType."]");

        foreach ($formElements as $n) {
            if ($n->getNodeData()->hasProperty('operand') && $n->getNodeData()->getProperty('operand') != '') $linkedWithConditionOperand[$n->getNodeData()->getProperty('operand')] = array('nodeType' => $n->getNodeType()->getName());
        }



        return json_encode($linkedWithConditionOperand);

    }





    /**
     * Get current form node
     *
     * @param Node $node
     * @return boolean
     */
    public function isCurrentFormNode(Node $node)
    {


      //  $node = $this->rewriteCurrentNode($node);

        if ($node->getContext()->isInBackend()) return true;

        if ($this->httpRequest->hasArgument('proceed') == false || $this->httpRequest->getArgument('proceed') == false) {


            if ($this->httpRequest->hasArgument('formNodeId') == false && $node->getNodeType()->getName() == 'Neoslive.Nodeform:Form') return true;
            if ($this->httpRequest->hasArgument('formNodeId') == true && $this->httpRequest->getArgument('formNodeId') == $node->getNodeData()->getIdentifier()) return true;

        } else {

           // if (isset($this->currentFormIds[$node->getIdentifier()])) {
                return true;
           // }

        }



        return false;


    }


    public function getFinisherNodes($node) {

        $nodes = array();




            $flowQuery = new FlowQuery(array($node));


            $finisherNodes = $flowQuery->find("[instanceof Neoslive.Nodeform:AppFormFinisherMixin]");
            $this->currentFormIds[$node->getIdentifier()] = true;

            foreach ($finisherNodes as $pn) {

                $flowQuery2 = new FlowQuery(array($pn));
                $finisherNodeParents = $flowQuery2->parentsUntil("[instanceof Neoslive.Nodeform:AppFormFinisherMixin]");

                foreach ($finisherNodeParents as $n) {

                    if ($this->httpRequest->getArgument('formNodeId') == $n->getParent()->getIdentifier() ) {
                        $nodes[] = $pn;
                    }
                }



           }


            return $this->restrictNodesToConditions($nodes,true);


    }


    /**
     * Get nodes respecting conditions
     *
     * @param Node $node
     * @return array
     */
    public function getFinisherElements(Node $node)
    {

      //  return $this->getElements($node,null,-1,-1,true);

    }



    /**
     * Get nodes respecting conditions
     *
     * @param Node $node
     * @param string $findAllNodesRecursivByNodeType
     * @return array
     */
    public function getElements(Node $node,$finisherMode=false)
    {


        if ($node->getContext()->isInBackend()) {
            // skip parental check, fetch all nodes
            return $node->getChildNodes();
        } else {


            $allowed = false;

            if ($finisherMode) {


                if ($this->httpRequest->hasArgument('proceed') && $this->httpRequest->hasArgument('formNodeId')) {

                    // in finisher mode
                    if ($node->getNodeType()->getName() == 'Neoslive.Nodeform:Form') {

                        $node = $this->rewriteToFinisherNode($node);

                        $flowQuery = new FlowQuery(array($node));
                        $finisherNodes = $flowQuery->find("[instanceof Neoslive.Nodeform:AppFormFinisherMixin]");

                        $finishers = array();
                        foreach ($finisherNodes as $n) {
                            if ($n->isRemoved() == false && $n->getParent() && $this->httpRequest->getArgument('formNodeId') == $this->getParentNodeByTypeUntilNodeType($n->getParent(), array('Neoslive.Nodeform:Form', 'Neoslive.Nodeform:Finisher'))->getIdentifier()) {
                                $finishers[] = $n;
                            }
                        }

                        $finisherNodes = array();
                        foreach ($finishers as $n) {
                            if ($this->checkAccessRecursive($n)) $finisherNodes[] = $n;
                        }

                        return $finisherNodes;

                    }

                    if (substr($node->getNodeType()->getName(), 0, 26) == 'Neoslive.Nodeform:Finisher') {
                        if ($this->httpRequest->getArgument('formNodeId') == $this->getParentNodeByTypeUntilNodeType($node, array('Neoslive.Nodeform:Form','Neoslive.Nodeform:Finisher'))->getIdentifier()) {
                            // if sub finisher
                            $allowed = true;

                       }
                    }


                } else if ($this->httpRequest->hasArgument('formNodeId')) {

                    // in update mode
                    $node = $this->rewriteToFinisherNode($node);
                    if ($node->getIdentifier() == $this->httpRequest->getArgument('formNodeId')) {
                        $allowed = true;
                    }
                } else {
                    // first call mode
                    // only allow nodes with Form as nodetype
                   if ($node->getNodeType()->getName() == 'Neoslive.Nodeform:Form') {
                        $allowed = true;
                    }

                }


            } else {

                // default call
                $allowed = true;
            }


            if ($allowed) {
                return $this->restrictNodesToConditions($node->getChildNodes());
            }


            return array();


        }




    }



    public function rewriteToFinisherNode($node) {

        // rewrite node to current request
        if ($this->httpRequest->hasArgument('formNodeId')) {
            $flowQuery = new FlowQuery(array($node));
            $result = $flowQuery->find('[instanceof Neoslive.Nodeform:AppFormFinisherMixin]');
            foreach ($result as $n) {
                if ($n->getIdentifier() == $this->httpRequest->getArgument('formNodeId')) {
                    $node = $n;
                }
            }
        }

        return $node;
    }


    /**
     * Filter nodes by restrictions
     *
     * @param array $allnodes
     * @return array
     */
    public function restrictNodesToConditions($allnodes,$finisherMode = false,$dontRestrictFinisherNodes = false)
    {




        // dont restrict nodes in backend
        if ($this->isBackend) return $allnodes;


        foreach ($allnodes as $k => $node) {



            $enabled = true;



            // dont filter nodes by conditions in backend

            switch ($node->getNodeType()->getName()) {

                case 'Neoslive.Nodeform:Then':
                case 'Neoslive.Nodeform:ThenInFormSelect':
                case 'Neoslive.Nodeform:ThenInFinisherMail':

                    $conditionId = $node->getParent()->getIdentifier();
                    $enabled = true;

                    if (isset($this->conditionsCalculated[$node->getParent()->getIdentifier()]) == false) {
                        $this->conditionsCalculated[$node->getParent()->getIdentifier()] = isset($this->isCondition($node->getParent())[$conditionId]) ? $this->isCondition($node->getParent())[$conditionId] : false;
                    }
                    if (!$this->conditionsCalculated[$node->getParent()->getIdentifier()]) $enabled = false;


                    break;

                case 'Neoslive.Nodeform:Else':
                case 'Neoslive.Nodeform:ElseInFormSelect':
                case 'Neoslive.Nodeform:ElseInFinisherMail':

                    $conditionId = $node->getParent()->getParent()->getIdentifier();
                    $enabled = false;

                    if (isset($this->conditionsCalculated[$node->getParent()->getIdentifier()]) == false) {
                        $this->conditionsCalculated[$node->getParent()->getIdentifier()] = isset($this->isCondition($node->getParent())[$conditionId]) ? $this->isCondition($node->getParent())[$conditionId] : true;
                    }

                    if ($this->conditionsCalculated[$node->getParent()->getIdentifier()] == false) $enabled = true;

                    break;


                case 'Neoslive.Nodeform:Constraints':
                case 'Neoslive.Nodeform:ConstraintsInFormSelect':

                    $enabled = false;

                    break;

                default:

                    $enabled = true;

                    break;

                }



                if (!$enabled) {
                    //$this->removeRegisteredNodes($allnodes[$k]->getIdentifier());
                    unset($allnodes[$k]);
                } else {
                    // register node as returned node
                    $this->addRegisteredNodes($allnodes[$k]->getIdentifier());
                }


        }

        return $allnodes;


    }


    /**
     * Check if node is affected from conditions
     *
     * @param Node $node
     * @return array
     */
    private function isCondition($node)
    {


        // show all nodes in backend mode
        if ($node->getContext()->isInBackend()) return true;

        $flowQuery = new FlowQuery(array($node->getParent()));
        $formConditionBaseNode = $flowQuery->find("[instanceof Neoslive.Nodeform:Constraints]","[instanceof Neoslive.Nodeform:ConstraintsInFormSelect]");


        $conditionTree = array();

        foreach ($formConditionBaseNode as $baseNode) {


            // wrapping condition node
            $conditionId = $this->getParentNodeByTypeUntilNodeType($baseNode, 'Neoslive.Nodeform:Condition')->getIdentifier();
            $conditionTree[$conditionId][$baseNode->getIdentifier()] = array(
                'id' => $baseNode->getIdentifier(),
                'parent_id' => null,
                'relation' => '',
                'calculation' => '',
                'operator' => '',
                'operand' => '',
                'nodetype' => $baseNode->getNodeType()->getName(),
                'value' => '',
                'valuekey' => ''

            );
            $this->conditionsCalculators[$baseNode->getIdentifier()] = $conditionTree[$conditionId][$baseNode->getIdentifier()];


            // condition children for tree creation
            $flowQuery = new FlowQuery(array($baseNode));
            $formConditionExpressionNodes = $flowQuery->find("[instanceof Neoslive.Nodeform:CompareMixin]");


            foreach ($formConditionExpressionNodes as $e) {


                if ($e->getParent()) {
                    $conditionId = $this->getParentNodeByTypeUntilNodeType($e, 'Neoslive.Nodeform:Condition')->getIdentifier();

                    $conditionTree[$conditionId][$e->getIdentifier()] = array(
                        'id' => $e->getIdentifier(),
                        'parent_id' => (substr_count($e->getParent()->getNodeType()->getName(), 'Neoslive.Nodeform:Constraints')) ? $e->getParent()->getIdentifier() : $e->getParent()->getParent()->getIdentifier(),
                        'relation' => ($e->getParent()->getParent()->getNodeType()->getName() == 'Neoslive.Nodeform:And' || $e->getParent()->getNodeType()->getName() == 'Neoslive.Nodeform:Constraints' ? 'AND' : 'OR'),
                        'calculation' => $e->getProperty('calculation'),
                        'operator' => $e->getNodeData()->getProperty('operator'),
                        'operand' => $e->getNodeData()->getProperty('operand'),
                        'nodetype' => $e->getNodeType()->getName(),
                        'value' => ($e->getNodeData()->getProperty('value') != '' ? $e->getNodeData()->getProperty('value') : $e->getNodeData()->getProperty('valuekey')),
                        'valuekey' => $e->getNodeData()->getProperty('value') == '' ? $e->getNodeData()->getProperty('valuekey') : '',
                        'test' => (substr_count($e->getParent()->getNodeType()->getName(), 'Neoslive.Nodeform:Constraints')),
                        'est' => $e->getParent()->getNodeType()->getName()


                    );

                    $this->conditionsCalculators[$e->getIdentifier()] = $conditionTree[$conditionId][$e->getIdentifier()];
                }


            }

        }


        $result_all = array();


        // condition calcuation

        foreach ($conditionTree as $cId => $cTree) {

                    if (isset($this->conditionsCalculatorsNestedTemp[$cId])) {
                        // get cached result
                        $result_all[$cId] = $this->conditionsCalculatorsNestedTemp[$cId];
                    } else {
                        // calculate conditions from tree
                        $cTreeNested = $this->makeNested($cTree);
                        $result = false;
                        eval("if (" . $this->makeNestedConditions($cTreeNested) . " ) " . '{ $result = true;} else { $result = false;}');
                        $result_all[$cId] = $result;
                        $this->conditionsCalculatorsNestedTemp[$cId] = $result;
                    }
           }


        return $result_all;


    }



    /**
     * get parent node by nodeTypeName
     *
     * @param Node $node
     * @param array $nodeTypeName
     * @return Node
     */
    public function getParentNodeByTypeUntilNodeType($node,$nodeTypeName,$findSubstring=true)
    {



        if (!is_array($nodeTypeName)) {
            $nodeTypeName = array($nodeTypeName);
        }


        $i = 0;

        $node = $node->getParent();

        while ($i<999999 && $node->getParent()) {

            if ($findSubstring) {
                foreach ($nodeTypeName as $ntn) {
                    if (substr_count($node->getNodeType()->getName(),$ntn)) return $node;
                }

            } else {
                foreach ($nodeTypeName as $ntn) {
                    if ($node->getNodeType()->getName() == $ntn) return $node;
                }
            }

            reset($nodeTypeName);

            $node = $node->getParent();
            $i++;
        }

        return $node;

    }

    /**
     * find wrapping nodetype in path
     *
     * @param array $allnodes
     * @return Node
     */
    public function getAllParentNodesByTypeUntilNodeType($node,$nodeTypeName,$nodeTypeNameUntil,$findSubstring=true)
    {

        if (!is_array($nodeTypeName)) {
            $nodeTypeName = array($nodeTypeName);
        }

        if (!is_array($nodeTypeNameUntil)) {
            $nodeTypeNameUntil = array($nodeTypeNameUntil);
        }


        $i = 0;
        $nodes = array();

        // TODO check it if parent is must --> while ($i<999999 && $node->getParent()) {
        while ($i<999999 && $node->getParent()) {

            if ($findSubstring) {

                foreach ($nodeTypeName as $ntn) {
                    if (substr_count($node->getNodeType()->getName(), $ntn)) $nodes[] = $node;
                }

                foreach ($nodeTypeNameUntil as $ntn) {
                    if (substr_count($node->getNodeType()->getName(), $ntn)) return $nodes;
                }

            } else {

                foreach ($nodeTypeName as $ntn) {
                    if ($node->getNodeType()->getName() == $ntn) $nodes[] = $node;
                }

                foreach ($nodeTypeNameUntil as $ntn) {

                    if ($node->getNodeType()->getName() == $ntn) {
                        return $nodes;
                    }
                }
            }

            reset($nodeTypeName);
            $node = $node->getParent();
            $i++;
        }

        return $nodes;

    }


    /**
     * find most top parent node by nodeType betwin same form
     *
     * @param array $allnodes
     * @return Node
     */
    public function getMostParent($node,$nodeTypeName)
    {



        if (!is_array($nodeTypeName)) {
            $nodeTypeName = array($nodeTypeName);
        }


        $result = $this->getAllParentNodesByTypeUntilNodeType($node,$nodeTypeName,array_merge($nodeTypeName,array('Neoslive.Nodeform:Form','Neoslive.Nodeform:FormFinisher')),false);

        return is_array($result) ? current($result) : null;


    }




    /**
     * check recursive if node is allowed in condition chain
     *
     * @param Node $node
     * @return boolean
     */
    public function checkAccessRecursive($node)
    {

        if ($node->isRemoved()) return false;
        if ($node->isHidden()) return false;

        $conditionalNodes = $this->getAllParentNodesByTypeUntilNodeType(
            $node,
            array('Neoslive.Nodeform:Then','Neoslive.Nodeform:Else'),
            array('Neoslive.Nodeform:Form'));
        if (count($this->restrictNodesToConditions($conditionalNodes),true) == count($conditionalNodes)) {
            return true;
        } else {
            return false;
        }

    }



    /**
     *  create nested array from parent-id-children tree
     *
     * @param array $source
     * @return array
     */
    private function makeNested($source)
    {
        $nested = array();

        foreach ($source as &$s) {
            if (is_null($s['parent_id'])) {
                // no parent_id so we put it in the root of the array
                $nested[] = &$s;
            } else {
                $pid = $s['parent_id'];
                if (isset($source[$pid])) {
                    // If the parent ID exists in the source array
                    // we add it to the 'children' array of the parent after initializing it.

                    if (!isset($source[$pid]['children'])) {
                        $source[$pid]['children'] = array();
                    }

                    $source[$pid]['children'][] = &$s;
                }
            }
        }
        return $nested;
    }


    /**
     *  create nested condition from parent-id-children tree
     *
     * @param array $source
     * @return array
     */
    private function makeNestedConditions($source)
    {

        $nested = '1 == 1 ';

        foreach ($source as $key => $val) {

            $nested .= $val['relation'] . ' $this->calculateCondition("' . $val['id'] . '") ';

            if (isset($val['children'])) {
                $nested .= "  AND ( " . $this->makeNestedConditions($val['children']) . ")";
            }


        }

        $nested .= '   ';

        return preg_replace("[1 == 1 AND|1 == 1 OR|1 == 1 ]","",$nested);

    }



    /**
     *  calculate condition
     *
     * @param string $conditionId
     * @return boolean
     */
    private function calculateCondition($conditionId)
    {


        if ($this->httpRequest->hasArgument('proceed') == true || $this->httpRequest->getArgument('proceed') == true) {
            // set request of current form and all previous form requests (finisher chain)
            $currentData = $this->httpRequestDataAll;
        } else {
            // set request of current form
            $currentData = $this->httpRequestData;
        }


        // calculate user input
        if (key_exists($conditionId,$this->conditionsCalculators)) {


            if ($this->conditionsCalculators[$conditionId]['value'] != '' && $this->conditionsCalculators[$conditionId]['operand'] != '') {


                if (($this->conditionsCalculators[$conditionId]['calculation'] == 'default' && $currentData->hasArgument($this->conditionsCalculators[$conditionId]['operand']) || $this->conditionsCalculators[$conditionId]['valuekey'] == 0)) {


                    switch ($this->conditionsCalculators[$conditionId]['operator']) {


                        default:


                             if (

                                 ($this->conditionsCalculators[$conditionId]['valuekey'] === '0' && $currentData->hasArgument($this->conditionsCalculators[$conditionId]['operand']) == false)

                                 ||


                                ($this->conditionsCalculators[$conditionId]['valuekey'] === '0' && $currentData->hasArgument($this->conditionsCalculators[$conditionId]['operand']) && $currentData->getArgument($this->conditionsCalculators[$conditionId]['operand']) == '')
                                 ||


                                 ($this->conditionsCalculators[$conditionId]['valuekey'] === '1' && $currentData->hasArgument($this->conditionsCalculators[$conditionId]['operand']) && ( $currentData->getArgument($this->conditionsCalculators[$conditionId]['operand']) != false && $currentData->getArgument($this->conditionsCalculators[$conditionId]['operand']) !== '' ))
                                 ||


                                 $currentData->getArgument($this->conditionsCalculators[$conditionId]['operand']) == $this->conditionsCalculators[$conditionId]['value']
                                 ||


                                 (
                                 is_array($currentData->getArgument($this->conditionsCalculators[$conditionId]['operand'])) && array_key_exists($this->conditionsCalculators[$conditionId]['value'],$currentData->getArgument($this->conditionsCalculators[$conditionId]['operand'])) && $currentData->getArgument($this->conditionsCalculators[$conditionId]['operand'])[$this->conditionsCalculators[$conditionId]['value']] == true
                                 )


                                 || (
                                 is_array($currentData->getArgument($this->conditionsCalculators[$conditionId]['operand'])) && in_array($this->conditionsCalculators[$conditionId]['value'],$currentData->getArgument($this->conditionsCalculators[$conditionId]['operand'])) && array_key_exists($this->conditionsCalculators[$conditionId]['value'],$currentData->getArgument($this->conditionsCalculators[$conditionId]['operand'])) == false
                                 )


                             ) {
                                 return true;
                             } else {
                                 return false;
                             }
                        break;
                    }

                 }

            } else {
                return true;
            }



        }



        return false;


    }

    /**
     * All methods are considered safe, i.e. can be executed from within Eel
     *
     * @param string $methodName
     * @return boolean
     */
    public function allowsCallOfMethod($methodName)
    {
        return TRUE;
    }


}