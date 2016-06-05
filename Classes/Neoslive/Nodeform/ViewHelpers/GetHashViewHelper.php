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
use TYPO3\TYPO3CR\Domain\Model\Node;
use TYPO3\TYPO3CR\Domain\Repository\NodeDataRepository;
use TYPO3\Flow\Http\Request;
use Neoslive\Nodeform\Eel\Helper\NodeHelper;

/**
 * A view helper for build form
 *
 * = Examples =
 *
 */
class GetHashViewHelper extends AbstractViewHelper
{


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
     * @return string The rendered property with a wrapping tag. In the user workspace this adds some required attributes for the RTE to work
     * @throws ViewHelperException
     */
    public function render($node)
    {

        $etagFile = $this->nodeHelper->getReturnedNodesHash();

        header("Etag: ".$etagFile);
        header("x-neoslive-nodeform-hash: ".$etagFile);

        if ($this->controllerContext->getRequest()->hasArgument('formNodeId')) header("x-neoslive-nodeform-formNodeId: ".$this->controllerContext->getRequest()->getArgument('formNodeId'));

        if ($this->controllerContext->getRequest()->hasArgument("x-neoslive-nodeform-hash") && $this->controllerContext->getRequest()->getArgument("x-neoslive-nodeform-hash") == $etagFile) {
            header("x-neoslive-nodeform-apply: ".$etagFile);
        }



    }





}
