<?php
namespace Neoslive\Nodeform\ViewHelpers;

/*
 * This file is part of the TYPO3.Neos package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3\Fluid\Core\ViewHelper\Exception as ViewHelperException;
use TYPO3\Eel\FlowQuery\FlowQuery;
use TYPO3\Flow\Http\Request;
use Neoslive\Nodeform\Eel\Helper\NodeHelper;

/**
 * A view helper for build form
 *
 * = Examples =
 *
 */
class FormStepsViewHelper extends AbstractViewHelper
{


    /**
     * @var Request
     */
    protected $httpRequest;


    /**
     * @var boolean
     */
    protected $escapeOutput = false;


    /**
     * @Flow\Inject
     * @var NodeHelper
     */
    protected $nodeHelper;

    /**
     * constructor.
     */
    public function __construct()
    {

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;

        $this->httpRequest = new Request($_GET, $input, $_FILES, $_SERVER);


    }


    /**
     * @return string The rendered property with a wrapping tag. In the user workspace this adds some required attributes for the RTE to work
     * @throws ViewHelperException
     */
    public function render($node)
    {

        $stepNodes = array();
        $stepNodes_sorted = array();

        $stepNodes[0][] = array(
            'name' => $node->getProperty('text'),
            'current' => $this->httpRequest->hasArgument('formNodeId') == false || ($this->httpRequest->hasArgument('formNodeId') && $this->httpRequest->hasArgument('proceed') == false && $this->httpRequest->getArgument('formNodeId') == $node->getIdentifier()) ? true : false,
            'Identifier' => $node->getIdentifier()
        );


        $flowQuery = new FlowQuery(array($node));
        $finisherNodes = $flowQuery->find("[instanceof Neoslive.Nodeform:AppFormFinisherMixin]");

        foreach ($finisherNodes as $n) {

            if ($this->nodeHelper->checkAccessRecursive($n)) {

                if ($n->getProperty('text')) {
                    $nf = $this->nodeHelper->getParentNodeByTypeUntilNodeType($n->getParent(),array('Neoslive.Nodeform:Form','Neoslive.Nodeform:Finisher'));
                    $stepNodes[substr_count($n->getPath(), "/")][] = array(
                        'name' => $n->getProperty('text'),
                        'current' => $this->httpRequest->getArgument('formNodeId') == $nf->getIdentifier() ? true : false,
                        'Identifier' => $n->getIdentifier()

                    );
                }
            }
        }


        // sort and reduce
        ksort($stepNodes);


        foreach ($stepNodes as $key => $val) {
            foreach ($val as $k => $v) {
                $stepNodes_sorted[] = $v;
            }
        }


        $this->templateVariableContainer->add('stepNodes',$stepNodes_sorted);



        return $this->renderChildren();

    }






}
