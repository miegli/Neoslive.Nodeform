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
use TYPO3\Flow\Resource\ResourceManager;
use Neoslive\Nodeform\DataSource\ConditionContraintsDataSource;
use Neoslive\Nodeform\Eel\Helper\NodeHelper;
use TYPO3\Flow\Http\Request;

/**
 * A view helper for build form
 *
 * = Examples =
 *
 */
class FormViewHelper extends AbstractViewHelper
{


    /**
     * @var boolean
     */
    protected $escapeOutput = false;

    /**
     * @Flow\Inject
     * @var ConditionContraintsDataSource
     */
    protected $conditionContraintsDataSource;

    /**
     * @Flow\Inject
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * @Flow\Inject
     * @var NodeHelper
     */
    protected $nodeHelper;


    /**
     * @var Request
     */
    protected $httpRequest;




    /**
     * ViewHelper constructor.
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



        if ($node->getContext()->isInBackend()) {
                return $this->includeJavascriptLoadersHtml($node). "\n".$this->replacePlaceholdersBackend($this->renderChildren(),$this->conditionContraintsDataSource->getData($node,array()));
        } else {


                if ($node->getNodeType()->getName() == 'Neoslive.Nodeform:Form' && $this->httpRequest->hasArgument('proceed')) {
                return $this->renderChildren();
                } else {
                return $this->includeJavascriptLoadersHtml($node). "\n".$this->replacePlaceholdersFrontend($this->renderChildren());

                }


        }



    }


    
    public function includeJavascriptLoadersHtml($node) {


        $files = array();


        if ($node->getContext()->isInBackend()) {
            $files["jqueryChosen"] = array(
                "type" => "javascript",
                "url" => $this->resourceManager->getPublicPackageResourceUri('TYPO3.Neos', 'Library/chosen/chosen/chosen.jquery.min.js'),
                "checkbeforeload" => array("undefined"),
                "onloaded" => true
            );
            $files["css"] = array(
                "type" => "css",
                "url" => $this->resourceManager->getPublicPackageResourceUri('Neoslive.Nodeform','css/backend.css'),
                "checkbeforeload" => array("undefined")
            );

        } else {


           if ($node->getNodedata()->getProperty('cssin') != '' | $node->getNodedata()->getProperty('cssout')) {

               $files["animatecss"] = array(
                   "type" => "css",
                   "url" => $this->resourceManager->getPublicPackageResourceUri('Neoslive.Nodeform', 'css/animate.min.css'),
                   "checkbeforeload" => array("undefined")
               );
           }

        }

        $files["jquery"] = array(
            "type" => "javascript",
            "url" => $this->resourceManager->getPublicPackageResourceUri('Neoslive.Nodeform','JavaScript/jquery.min.js'),
            "checkbeforeload" => array("jQuery")
        );


        $files["angular"] = array(
            "type" => "javascript",
            "url" => $this->resourceManager->getPublicPackageResourceUri('Neoslive.Nodeform','JavaScript/angular.min.js'),
            "checkbeforeload" => array("angular")
        );

        $files["angularcookies"] = array(
            "type" => "javascript",
            "url" => $this->resourceManager->getPublicPackageResourceUri('Neoslive.Nodeform','JavaScript/angular.cookies.min.js'),
            "checkbeforeload" => array("undefined")
        );

        $files["angularsantize"] = array(
            "type" => "javascript",
            "url" => $this->resourceManager->getPublicPackageResourceUri('Neoslive.Nodeform','JavaScript/angular-sanitize.min.js'),
            "checkbeforeload" => array("undefined")
        );

        $files["neosliveform"] = array(
            "type" => "javascript",
            "url" => $this->resourceManager->getPublicPackageResourceUri('Neoslive.Nodeform','JavaScript/form.app.js'),
            "checkbeforeload" => array("NeosliveNodeformApp"),
            "async" => false
        );


        $initvars['labels'] = array("insertPlaceholder" => "Insert Placeholders");
        $initvars['nodesInCondition'] = $this->nodeHelper->getElementsLinkedWithConditions($node,'Neoslive.Nodeform:CompareMixin');

        $initvars['init'] = array('Identifier'=>$node->getNodeData()->getIdentifier(),'nodePath'=>$node->getPath(),'workspace'=>$node->getContext()->getWorkspace()->getName(),'instanceid' => 0,'contextPath' => $node->getContextPath());

        $html =  '<noscript class="neoslive-nodeform-initvars">'.json_encode($initvars).'</noscript>'."\n";
        $html .=  '<noscript class="neoslive-nodeform-includes">'.json_encode($files).'</noscript>'."\n";
        $html .= '<script src="'.$this->resourceManager->getPublicPackageResourceUri('Neoslive.Nodeform','JavaScript/jquery.min.js').'"></script>'."\n";
        $html .= '<script src="'.$this->resourceManager->getPublicPackageResourceUri('Neoslive.Nodeform','JavaScript/form.bootstrap.js').'"></script>'."\n";

        return $html;
        
    }




    public function replacePlaceholdersFrontend($html) {

        // 0 = full
        // 2 = href
        // 4 = nodeid
        // 6 = linktext
        preg_match_all('/<a([^>]*)href="([^>]*)" ([^>]*)neosliveformnode-placholder-([A-z0-9-]*)-id([^>]*)>([^>]*)<\/a>/',$html,$matches);


        foreach ($matches[0] as $i => $match) {

            if ($matches[2][$i] == '#') {
                // remove link
                $data = '<span ng-bind="(data[\''.$matches[4][$i].'\'] ) | placeholder:this"></span>';

            } else {
                // preserve link
                preg_match('/<a([^>]*)target="([^>]*)" ([^>]*)neosliveformnode-placholder-([A-z0-9-]*)-id([^>]*)>([^>]*)<\/a>/', $matches[0][$i], $m);

                if (isset($m[1])) {
                    $target = 'target="' . $m[1] . '"';
                } else {
                    $target = '';
                }

                $data = '<a href="' . $matches[2][$i] . '" ' . $target . ' ng-bind="(data[\''.$matches[4][$i].'\'] ) | placeholder:this"></a>';

            }

            $html = str_replace($match,$data,$html);

        }

        return $html;

    }

    public function replacePlaceholdersBackend($html,$placeholders) {

        // 0 = full
        // 2 = href
        // 4 = nodeid
        // 6 = linktext
       preg_match_all('/<a([^>]*)href="([^>]*)" ([^>]*)neosliveformnode-placholder-([A-z0-9-]*)-id([^>]*)>([^>]*)<\/a>/',$html,$matches);

        foreach ($matches[0] as $i => $match) {

                // preserve link
                preg_match('/<a([^>]*)target="([^>]*)" ([^>]*)neosliveformnode-placholder-([A-z0-9-]*)-id([^>]*)>([^>]*)<\/a>/',$matches[0][$i],$m);

                if (isset($m[1])) {
                    $target='target="'.$m[1].'"';
                } else {
                    $target = '';
                }

            if (isset($placeholders[$matches[4][$i]])) {
                $data = '<a href="'.$matches[2][$i].'" '.$target.' class="neosliveformnode-placholder neosliveformnode-placholder-'.$matches[4][$i].'-id">'.$placeholders[$matches[4][$i]]['label'].'</a>';
                $html = str_replace($match,$data,$html);

            } else {}



        }

        return $html;

    }


}
