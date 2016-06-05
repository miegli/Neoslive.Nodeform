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
use Neoslive\Nodeform\Session\FinisherMail;
use TYPO3\Flow\Http\Request;

/**
 * A view helper for build form
 *
 * = Examples =
 *
 */
class FinisherMailViewHelper extends AbstractViewHelper
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
     * @var FinisherMail
     */
    protected $finisherMail;


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
    public function render($node,$action=false)
    {

        $flowQuery = new FlowQuery(array($node));
        $baseNode = $flowQuery->closest("[instanceof Neoslive.Nodeform:FinisherMail]")->get(0);
        $identifier = $baseNode->getIdentifier();



        switch ($action) {


            case 'addRecipient':

             $this->finisherMail->addRecipient(array(
                    'mail' => $node->getProperty('mail'),
                    'name' => $node->getProperty('name'),
                    'maildynamic' => $node->getProperty('maildynamic'),
                    'namedynamic' => $node->getProperty('namedynamic')
                ),$identifier,$this->httpRequest);

            break;

            case 'addSubject':
                $this->finisherMail->addSubject(array(
                    'subject' => $node->getProperty('subject')
                ),$identifier,$this->httpRequest);


            break;

            case 'sendMail':

                // prepare mail sender
                $this->finisherMail->addSender(array(
                    'mail' => $node->getProperty('mail'),
                    'name' => $node->getProperty('name'),
                    'maildynamic' => $node->getProperty('maildynamic'),
                    'namedynamic' => $node->getProperty('namedynamic')
                ),$identifier,$this->httpRequest);




                // prepare mail body (must renderChildren here to get recipients and subject)
                $body = $this->replacePlaceholders($this->renderChildren(),$this->httpRequest);


                // proceed mail sending
                if ($this->finisherMail->isValid($identifier)) {


                    $subjects = $this->finisherMail->getSubjects($identifier);

                    // set default subject if not set
                    if (is_array($subjects) === false) {


                        $flowQuery2 = new FlowQuery(array($baseNode));
                        $pageNode = $flowQuery2->closest("[instanceof TYPO3.Neos.NodeTypes:Page]")->get(0);
                        if ($pageNode) {
                            $subjects[] = array('subject'=>$pageNode->getProperty('title'). " - ".$baseNode->getContext()->getCurrentSite()->getName());
                        } else {
                            $subjects[] = array('subject'=>$baseNode->getContext()->getCurrentSite()->getName());
                        }


                    }

                    foreach ($subjects as $subject) {

                        $mail = new \TYPO3\SwiftMailer\Message();

                        foreach ($this->finisherMail->getRecipients($identifier) as $recipient) {

                            if (filter_var($recipient['mail'], FILTER_VALIDATE_EMAIL)) {
                                $mail->addTo($recipient['mail'], $recipient['name']);
                            }

                        }

                        $sender = $this->finisherMail->getSender($identifier,$node->getContext());
                        $mail->setFrom(array(
                             (string)$sender['mail'] => (string)$sender['name']
                            )
                        );

                        $mail->setSubject($subject['subject']);

                        if (trim(strip_tags($body)) == '') {
                            // set default body if not set
                            $body = $this->replacePlaceholders($baseNode->getProperty('confirmationtext'),$this->httpRequest);
                        }

                        $mail->setBody($body, 'text/html');
                        $mail->send();


                    }

                }

                $this->finisherMail->clear($identifier);



            break;


        }



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
