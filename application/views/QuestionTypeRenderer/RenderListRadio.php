<?php

/**
 * RenderClass for Boilerplate Question
 *  * The ia Array contains the following
 *  0 => string qid
 *  1 => string sgqa
 *  2 => string questioncode
 *  3 => string question
 *  4 => string type
 *  5 => string gid
 *  6 => string mandatory,
 *  7 => string conditionsexist,
 *  8 => string usedinconditions
 *  0 => string used in group.php for question count
 * 10 => string new group id for question in randomization group (GroupbyGroup Mode)
 *
 */
class RenderListRadio extends QuestionBaseRenderer
{
    public $sCoreClass = "ls-answers answers-list radio-list";
    
    protected $sOthertext;
    protected $iNbCols;
    protected $iColumnWidth;
    protected $iCountAnswers;

    private $iMaxRowsByColumn;
    private $iRowCount = 0;
    private $bColumnIsOpen = false;
    private $inputnames = [];
    
    public function __construct($aFieldArray, $bRenderDirect = false)
    {
        parent::__construct($aFieldArray, $bRenderDirect);
        $this->sOthertext = $this->setDefaultIfEmpty($this->aQuestionAttributes['other_replace_text'][$this->sLanguage], gT('Other:')); // text for 'other'
        $this->iNbCols   = @$this->setDefaultIfEmpty($this->aQuestionAttributes['display_columns'], 1); // number of columns
        $this->setAnsweroptions(null, $this->aQuestionAttributes['alphasort']==1);

        if ($this->iNbCols > 1) {
            // Add a class on the wrapper
            $this->sCoreClass .= " multiple-list nbcol-{$this->iNbCols}";
            // First we calculate the width of each column
            // Max number of column is 12 http://getbootstrap.com/css/#grid
            $this->iColumnWidth = round(12 / $this->iNbCols);
            $this->iColumnWidth = ($this->iColumnWidth >= 1) ? $this->iColumnWidth : 1;
            $this->iColumnWidth = ($this->iColumnWidth <= 12) ? $this->iColumnWidth : 12;
            $this->iMaxRowsByColumn = ceil($this->getAnswerCount() / $this->iNbCols);
        } else {
            $this->iColumnWidth = 12;
            $this->iMaxRowsByColumn = $this->getAnswerCount() + 3; // No max : anscount + no answer + other + 1 by security
        }
    }

    public function getMainView()
    {
        return '/survey/questions/answer/listradio';
    }

    public function renderRowsArray(){
        $aRows = [];
        foreach ($this->aAnswerOptions[0] as $iterator=>$oAnswer) {
            $aRows[] = Yii::app()->twigRenderer->renderQuestion($this->getMainView().'/rows/answer_row', array(
                'name'          => $this->sSGQA,
                'code'          => $oAnswer->code,
                'answer'        => $oAnswer->answerL10ns[$this->sLanguage]->answer,
                'checkedState'  => ($this->mSessionValue == $oAnswer->code ? 'CHECKED' : ''),
                'myfname'       => $this->sSGQA.$oAnswer->code,
                ), true);
        }

        if ($this->oQuestion->other == 'Y') {
            $aRows[] = $this->addOtherRow();
        }

        if ($this->oQuestion->mandatory != 'Y' && SHOW_NO_ANSWER == 1) {
            $aRows[] = $this->addNoAnswerRow();
        }

        return $aRows;
    }

    public function getRows()
    {
        $sRows = "";
        
        foreach($this->renderRowsArray() as $iterator => $sRow){
            // counter of number of row by column. Is reset to zero each time a column is full.
            $this->iRowCount++;
                
            ////
            // Open Column
            // The column is opened if user set more than one column in question attribute
            // and if this is the first answer row, or if the column has been closed and the row count reset before.
            if ($this->iRowCount == 1) {
                $sRows  .= Yii::app()->twigRenderer->renderQuestion(
                        $this->getMainView().'/columns/column_header',
                        array('iColumnWidth' => $this->iColumnWidth),
                        true
                    );
                $this->bColumnIsOpen  = true; // If a column is not closed, it will be closed at the end of the process
            }
        
            ////
            // Insert row
            // Display the answer row
            $sRows .= $sRow;

            ////
            // Close column
            // The column is closed if the user set more than one column in question attribute
            // and if the max answer rows by column is reached.
            // If max answer rows by column is not reached while there is no more answer,
            // the column will remain opened, and it will be closed by 'other' answer row if set or at the end of the process
            if ($this->iRowCount == $this->iMaxRowsByColumn) {
                $last      = ($iterator == $this->getAnswerCount()) ? true : false; // If this loop count equal to the number of answers, then this answer is the last one.
                $sRows  .= Yii::app()->twigRenderer->renderQuestion(
                        $this->getMainView().'/columns/column_footer',
                        array('last'=>$last),
                        true
                    );
                $this->iRowCount = 0;
                $this->bColumnIsOpen    = false;
            }
        }

        return $sRows;
    }

    public function addNoAnswerRow(){
        if (!isset($this->mSessionValue) || $this->mSessionValue == '' || $this->mSessionValue == ' ') {
            $check_ans = CHECKED; //Check the "no answer" radio button if there is no answer in session.
        } else {
            $check_ans = '';
        }

        return Yii::app()->twigRenderer->renderQuestion( $this->getMainView().'/rows/answer_row_noanswer', array(
            'name'=>$this->sSGQA,
            'check_ans'=>$check_ans,
            'checkconditionFunction'=>$this->checkconditionFunction,
            ), true);
    }

    public function addOtherRow(){
        $sSeparator = getRadixPointData($this->oQuestion->survey->correct_relation_defaultlanguage->surveyls_numberformat);
        $sSeparator = $sSeparator['separator'];
        
        $oth_checkconditionFunction = ($this->aQuestionAttributes['other_numbers_only'] == 1) ? 'fixnum_checkconditions' : 'checkconditions';   
        $checkedState = ($this->mSessionValue == '-oth-') ? CHECKED : '';

        $myfname = $thisfieldname = $this->sSGQA.'other';

        if (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$thisfieldname])) {
            $dispVal = $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$thisfieldname];
            if ($this->aQuestionAttributes['other_numbers_only'] == 1) {
                $dispVal = str_replace('.', $sSeparator, $dispVal);
            }
            $answer_other = ' value="'.htmlspecialchars($dispVal, ENT_QUOTES).'"';
        } else {
            $answer_other = ' value=""';
        }

        $this->inputnames[] = $thisfieldname;
        
        return Yii::app()->twigRenderer->renderQuestion($this->getMainView().'/rows/answer_row_other', array(
            'name' => $this->sSGQA,
            'answer_other'=>$answer_other,
            'myfname'=>$myfname,
            'othertext'=>$this->sOthertext,
            'checkedState'=>$checkedState,
            'oth_checkconditionFunction'=>$oth_checkconditionFunction.'(this.value, this.name, this.type)',
            'checkconditionFunction'=>$this->checkconditionFunction,
            ), true);

    }


    public function render($sCoreClasses = '')
    {
        $answer = '';
        $this->inputnames[] = $this->sSGQA;
        $this->sCoreClass .= " ".$sCoreClasses;

        if (!empty($this->aQuestionAttributes['time_limit']['value'])) {
            $answer .= $this->getTimeSettingRender();
        }

        $answer .=  Yii::app()->twigRenderer->renderQuestion($this->getMainView().'/answer', array(
            'sRows'     => $this->getRows(),
            'name'      => $this->sSGQA,
            'basename'  => $this->sSGQA,
            'value'     => $this->mSessionValue,
            'coreClass' => $this->sCoreClass
        ), true);

        return array($answer, $this->inputnames);
    }


    protected function getAnswerCount($iScaleId=0)
    {
        // Getting answerrcount
        $anscount  = count($this->aAnswerOptions[0]);
        $anscount  = ($this->oQuestion->other == 'Y') ? $anscount + 1 : $anscount; //COUNT OTHER AS AN ANSWER FOR MANDATORY CHECKING!
        $anscount  = ($this->oQuestion->mandatory != 'Y' && SHOW_NO_ANSWER == 1) ? $anscount + 1 : $anscount; //Count up if "No answer" is showing
        return $anscount;
    }
}
