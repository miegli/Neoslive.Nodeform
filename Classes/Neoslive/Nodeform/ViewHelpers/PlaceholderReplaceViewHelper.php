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
use TYPO3\Flow\Http\Request;

/**
 * A view helper for build form
 *
 * = Examples =
 *
 */
class PlaceholderReplaceViewHelper extends AbstractViewHelper
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
     * constructor.
     */
    public function __construct()
    {

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;


        if (is_array($input) && array_key_exists('dataAll',$input)) {
            $this->httpRequest = new Request($_GET, $input['dataAll'], $_FILES, $_SERVER);
        } else {
            $this->httpRequest = new Request($_GET, $input, $_FILES, $_SERVER);;
        }


    }


    /**
     * @return string The rendered property with a wrapping tag. In the user workspace this adds some required attributes for the RTE to work
     * @throws ViewHelperException
     */
    public function render()
    {

        return $this->replacePlaceholders(htmlspecialchars_decode($this->renderChildren()),$this->httpRequest);


    }


    private function replacePlaceholders($html,$request) {

        // 0 = full
        // 2 = href
        // 4 = nodeid
        // 6 = linktext
        preg_match_all('/<a([^>]*)href="([^>]*)" ([^>]*)neosliveformnode-placholder-([A-z0-9-]*)-id([^>]*)>([^>]*)<\/a>/',$html,$matches);


        foreach ($matches[0] as $i => $match) {

            if ($matches[2][$i] == '#') {
                // remove link
                $data = strip_tags($request->getArgument($matches[4][$i]));

            } else {
                // preserve link
                preg_match('/<a([^>]*)target="([^>]*)" ([^>]*)neosliveformnode-placholder-([A-z0-9-]*)-id([^>]*)>([^>]*)<\/a>/', $matches[0][$i], $m);

                if (isset($m[1])) {
                    $target = 'target="' . $m[1] . '"';
                } else {
                    $target = '';
                }

                $data = '<a href="' . $matches[2][$i] . '" ' . $target . '>'.strip_tags($request->getArgument($matches[4][$i])).'</a>';

            }

            $html = str_replace($match,$data,$html);

        }

        return $html;

    }




}
