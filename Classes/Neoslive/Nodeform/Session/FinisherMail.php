<?php
namespace Neoslive\Nodeform\Session;


use TYPO3\Flow\Annotations as Flow;


/**
 * @Flow\Scope("session")
 */
class FinisherMail {



    /**
     * @var array
     */
    protected $recipients = array();
    
    /**
     * @var array
     */
    protected $senders = array();

    /**
     * @var array
     */
    protected $subjects = array();






    /**
     * check if session has valid data to send mail
     * @return boolean
     */
    public function isValid($identifier) {

        if (count($this->getRecipients($identifier)) && count($this->getSubjects($identifier))) {
            return true;
        }

        return false;

    }


    /**
     * @param string $recipient
     * @return void
     * @Flow\Session(autoStart = TRUE)
     */
    public function clear($identifier) {
        unset($this->recipients[$identifier]);
        unset($this->subjects[$identifier]);
    }


    /**
     * @param string $recipient
     * @return void
     * @Flow\Session(autoStart = TRUE)
     */
    public function addRecipient($recipient,$identifier,$request) {

        $recipient['maildynamic'] = $request->getArgument($recipient['maildynamic']);

        $namedynamic = '';
        if (is_array($recipient['namedynamic'] )) {
            foreach ($recipient['namedynamic'] as $key => $val) {
                if ($key > 0) $namedynamic .= ' ';
                $namedynamic .= $request->getArgument($val);
            }
        }
        $recipient['namedynamic'] = $namedynamic;



        $this->recipients[$identifier][] = $recipient;
    }

    /**
     * @return array
     */
    public function getRecipients($identifier) {

        $output = array();

        $r = isset($this->recipients[$identifier]) ? $this->recipients[$identifier] : false;

        if (is_array($r)) {foreach ($r as $data) {
            if ($data && $data['maildynamic'] != '' && $data['namedynamic'] != '') {
                $output[] = array('name' => $data['namedynamic'], 'mail' => $data['maildynamic']);
            }

            if ($data && $data['mail'] != '' && $data['name'] != '') {
                $output[] = array('name' => $data['name'], 'mail' => $data['mail']);
            }
        }
        }


        return $output;
    }   


    /**
     * @param string $sender
     * @return void
     * @Flow\Session(autoStart = TRUE)
     */
    public function addSender($sender,$identifier,$request) {

        $sender['maildynamic'] = $request->getArgument($sender['maildynamic']);

        $namedynamic = '';
        if (is_array($sender['namedynamic'] )) {
            foreach ($sender['namedynamic'] as $key => $val) {
                if ($key > 0) $namedynamic .= ' ';
                $namedynamic .= $request->getArgument($val);
            }
        }
        $sender['namedynamic'] = $namedynamic;

        $this->senders[$identifier][] = $sender;
    }

    /**
     * @return array
     */
    public function getSenders($identifier) {

        $output = array();

        $r = isset($this->senders[$identifier]) ? $this->senders[$identifier] : null;
        if (is_array($r)) {
            foreach ($r as $data) {
                if ($data && $data['maildynamic'] != '' && $data['namedynamic'] != '') {
                    $output[] = array('name' => $data['namedynamic'], 'mail' => $data['maildynamic']);
                }

                if ($data && $data['mail'] != '' && $data['name'] != '') {
                    $output[] = array('name' => $data['name'], 'mail' => $data['mail']);
                }
            }
        }


        return $output;
    }


    /**
     * @return array
     */
    public function getSender($identifier,$context) {

        $senders = $this->getSenders($identifier);


        if (count($senders) && filter_var($senders[0]['mail'], FILTER_VALIDATE_EMAIL)) {
            return $senders[0];
        } else {
            return array('name'=>$context->getCurrentSite()->getName(),'mail'=>'info@'.$context->getCurrentDomain()->getHostPattern());
        }


    }
            
     /**
     * @param string $subject
     * @return void
     * @Flow\Session(autoStart = TRUE)
     */
    public function addSubject($subject,$identifier,$request) {
        $subject['subject'] = strip_tags($this->replacePlaceholders($subject['subject'],$request));
        $this->subjects[$identifier][] = $subject;
    }

    /**
     * @return array
     */
    public function getSubjects($identifier) {
        return isset($this->subjects[$identifier]) ? $this->subjects[$identifier] : false;
    }



    public function replacePlaceholders($html,$request) {

        // 0 = full
        // 2 = href
        // 4 = nodeid
        // 6 = linktext
        preg_match_all('/<a([^>]*)href="([^>]*)" ([^>]*)neosliveformnode-placholder-([A-z0-9-]*)-id([^>]*)>([^>]*)<\/a>/',$html,$matches);

        foreach ($matches[0] as $i => $match) {

            $data = strip_tags($request->getArgument($matches[4][$i]));
            $html = str_replace($match,$data,$html);

        }

        return $html;

    }
    

}