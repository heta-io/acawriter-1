<?php
/**
 * Project: AcaWriter
 *
 * Copyright(c)2018 original University of Technology Sydney.
 * Licensed under the Apache License, Version2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *
 * See the License for the specific language governing permissions and limitations under the License.
 *
 *  Contributor(s):
 *  UTS Connected Intelligence Centre
 */

namespace App\Traits;
use App\Services\Analyser;

trait Profiler {

    public function moves($tap, $rule) {

        $result = array();
        $check = $rule['check'];
        $tempo = 0;
        $tags = $check['tags'];
        $messages = $rule['message'];
        if(count($tags) == 0) {
            return $result;
        }

        foreach($tap as $key => $data) {
            $setFeed = new \stdClass();
            $setFeed->str = $data->str;
            $setFeed->message = array();
            $setFeed->css = array();

            foreach($tags as $tag) {
                if(count(preg_grep("[^".$tag."]", $data->raw_tags)) > 0) {
                    foreach($messages as $msg) {
                        if(isset($msg[$tag])) {
                            $setFeed->message[$tag] = $msg[$tag];
                            array_push($setFeed->css,$tag);
                        }
                    }
                }
            }
            $result[] = $setFeed;
        }

        return $result;
    }

    /**
     * @param $tap
     * @param $rule --- added to display static msg into the tab's
     * @return array
     */

    public function staticFeed($tap, $rule) {
        $result = array();
        $check = $rule['check'];
        $tempo = 0;
        $tags = $check['tags'];
        $messages = $rule['message'];

        $monitor = array();
        $issues = array();

        if($rule["tabEval"] === 'dynamic') {

        } else {
            foreach($messages as $key => $msg) {
                array_push($issues, $msg['txt']);
            }
        }
        array_push($result, $issues);
        return $result;
    }

    public function background($tap, $rule) {
        $result = array();
        $check = $rule['check'];
        $tempo = 0;

        foreach($tap as $key => $data) {
            $setFeed = new \stdClass();
            $setFeed->str = $data->str;
            $setFeed->message = array();
            $setFeed->css = array();
            if ($key < $check['paragraph'] && count($data->raw_tags) > 0) {
                if (in_array($check['paragraph'], $data->raw_tags)) {
                    $tempo++;
                }
            }
        }

        if ($tempo == 0 && $key == $this->para - 1) {
            $setFeed->message['background'] = $rule['message'];
            $setFeed->css[] = 'background';
            $result[] = $setFeed;
        }

        return $result;
    }

    public function metrics($tap, $rule) {
        $analyser = new Analyser();
        $result = array();
        $check = $rule['check'];

        foreach($tap as $key => $data) {
            $tempStore = new \stdClass();
            $tempStore->str = $data->str;
            $tempStore->message = array();
            $tempStore->css = array();
            $returnData = $analyser->metrics($data->str);
            if(isset($returnData->sentWordCounts)) {
                //sentWordCounts is always an array e.g. [5,6] if two sentences sent here we send only one at a time though
                if($returnData->sentWordCounts[0] > $check['sentenceWordCount']) {
                    //$tempStore->message = $rule['message'];
                    foreach($rule['message'] as $msg) {
                        if(isset($msg['metrics'])) {
                            $tempStore->message['metrics'] = $msg['metrics'];
                            array_push($tempStore->css, 'metrics');
                        }
                    }
                }
            }
            $result[] = $tempStore;
        }
        return $result;

    }

    /**
        * function works on the complete text at once, so input is not tokenised
        * so str = append all tokenised tap outputs into one
    */

    public function vocab($tap, $rule) {
        $analyser = new Analyser();
        $result = array();
        $check = $rule['check'];
        $termCount = 0;
        $words = $check['words'];
        $completeText = "";

        foreach($tap as $key => $data) {
            $completeText .= $data->str;
        }
        $tempStore = new \stdClass();
        $tempStore->str = $completeText;
        $tempStore->message = array();
        $tempStore->css =array();

        $returnData = $analyser->vocab($tempStore->str);
        if(isset($returnData->terms)) {
            $collection = collect($returnData->terms);

            foreach($check['words'] as $word) {
                $filtered= $collection->where('term', $word);
                if(count($filtered->all()) == 0) $termCount++;
            }

            if($termCount > 0 ) {
                foreach($rule['message'] as $msg) {
                    if(isset($msg['metrics'])) {
                        $tempStore->message['vocab'] = $msg['vocab'];
                        $tempStore->css[] = 'vocab';
                    }
                }
                $result[] = $tempStore;
            }
        }

        return $result;
    }

    /**
     * Used retrieve expressions
     * input: string single sentence
     * normally only used for reflective feedback
     * output is an array
    */

    protected function expression($tap, $rule) {

        $analyser = new Analyser();
        $result = array();
        $check = $rule['check'];
        $termCount = 0;
        $all = $check['all'];
        //fetch affect values
        $affectValues = $check["affectVal"]? $check["affectVal"] :'';

        if(count($all) == 0) {
            return $result;
        }

        foreach($tap as $key => $data) {
            $tempStore = new \stdClass();
            $tempStore->str = $data->str;
            $tempStore->message = array();
            $tempStore->affect=array();
            $tempStore->epistemic=array();
            $tempStore->modal=array();
            $tempStore->css = array();

            $returnData = $analyser->expression($data->str, $affectValues);
            //$returnData is an array but since we are analysing tokenised strings we can safely assume array[0]
            $sanitizedResult = $returnData[0];
            //$tempStore->raw = $sanitizedResult;
            foreach($all as $exp) {

                if (isset($sanitizedResult->{$exp}) && count($sanitizedResult->{$exp}) > 0) {
                    $tempStore->{$exp} = $sanitizedResult->{$exp};
                    foreach($rule['message'] as $msg) {
                        if(isset($msg[$exp])) {
                            $tempStore->message[$exp] = $msg[$exp];
                            array_push($tempStore->css, $exp);
                        }
                    }
                }
            }

            $result[] = $tempStore;
        }

        return $result;
    }
}