<?php
/* 
*  PHP Implementation of RAKE - Rapid Automtic Keyword Exraction algorithm as described in:
*  Rose, S., D. Engel, N. Cramer, and W. Cowley (2010). 
*  Automatic keyword extraction from individual documents. 
*  In M. W. Berry and J. Kogan (Eds.), Text Mining: Applications and Theory: John Wiley and Sons, Ltd.
*
*  Original Python implementation at https://github.com/aneesha/RAKE
*  Translated to PHP by Alexander Wesolowski (https://github.com/foglerek)
*/

class Rake {

    function __construct() 
    {
        // Defaults
        $this->bDebug = false;
        $this->sStopWordFilePath = "";
        $this->aStopWordList = array();
        $this->nMinWordCharacters = 0;
        $this->nMaxKeywordLimit = 0;
    }

    public function setDebug($bVal) {
        if (gettype($bVal) !== "boolean") { throw new Exception('setDebug() expects first argument to be of type boolean, got '.gettype($bVal)); }
        $this->bDebug = $bVal;
    }

    public function setStopWordFilePath($sVal) {
        if (gettype($sVal) !== "string") { throw new Exception('setStopWordFilePath() expects first argument to be of type string, got '.gettype($sVal)); }
        $this->sStopWordFilePath = $sVal;
    }

    public function setStopWordList($aVal) {
        if (gettype($sVal) !== "array") { throw new Exception('setStopWordList() expects first argument to be of type array, got '.gettype($aVal)); }
        $this->aStopWordList = $aVal;
    }

    public function setWordMinCharacters($nVal) {
        if (gettype($nVal) !== "integer") { throw new Exception('setWordMinCharacters expects first argument to be of type integer, got '.gettype($nVal)); }
        $this->nMinWordCharacters = $nVal;
    }

    public function setMaxKeywordLimit($nVal) {
        if (gettype($nVal) !== "integer") { throw new Exception('setMaxKeywordLimit expects first argument to be of type integer, got '.gettype($nVal)); }
        $this->nMaxKeywordLimit = $nVal;
    }

    /**
    * Main function. Takes a string of text as a minimum, and returns an array with generated keywords.
    * @param string $sText String of text to analyse and generate keywords from.
    * @param mixed $mStopWords (Optional) Array List with stopwords or String path to file with stopwords. Can also be set using the setter methods. Will default to the preset values if no argument or an empty argument is given.
    * @param integer $nMaxKeywordLimit (Optional) The maximum number of keywords to return. Can also be set using the setter method. Will default to returning 1/3 of the total number of keywords generated if no argument or an empty argument is given.
    * @param integer $nMinWordCharacters (Optional) The minimum number of characters for a word to be considered a keyword. Can also be set using the setter method. Will default to the preset value if no argument or an empty argument is given.
    * @return array Array with keywords sorted by keyword weight descending.
    */
    public function generateKeywords($sText, $mStopWords = null, $nMaxKeywordLimit = null, $nMinWordCharacters = null)
    {
        // Check arguments & set defaults
        if (gettype($sText) !== "string") { throw new Exception('generateKeywords() expects first argument to be of type string, got '.gettype($sText)); }

        if (!empty($mStopWords)) {
            if (gettype($mStopWords) === "array") { $aStopWordList = $mStopWords; }
            else if (gettype($mStopWords) === "string") { $aStopWordList = $this->loadStopWordsFromFile($mStopWords); }
            else { throw new Exception('generateKeywords() expects second argument to be of type array or string, got '.gettype($mStopWords)); }
        } else {
            $sStopWordFilePath = $this->sStopWordFilePath;
            $aStopWordList = empty($this->aStopWordList) ? $this->loadStopWordsFromFile($this->sStopWordFilePath) : $this->aStopWordList;
        }

        if (!empty($nMaxKeywordLimit)) {
            if (!gettype($nMaxKeywordLimit) === "integer") { throw new Exception('generateKeywords() expects third argument to be of type integer, got '.gettype($nMaxKeywordLimit)); }
        } else {
            $nMaxKeywordLimit = $this->nMaxKeywordLimit;
        }

        if (!empty($nMinWordCharacters)) {
            if (!gettype($nMinWordCharacters) === "integer") { throw new Exception('generateKeywords() expects fourth argument to be of type integer, got '.gettype($nMinWordCharacters)); }
        } else {
            $nMinWordCharacters = $this->nMinWordCharacters;
        }

        // Split sentences
        $aSentenceList = $this->splitSentences($sText);

        // Build Stopword Pattern
        $sStopWordPattern = $this->buildStopwordRegExPatternFromList($aStopWordList);

        // Generate Candidate Keywords
        $aCandidateKeywords = $this->generateCandidateKeywords($aSentenceList, $sStopWordPattern);

        // Calculate Individual Word Scores
        $aWordScores = $this->calculateWordScores($aCandidateKeywords, $nMinWordCharacters);

        // Calculate Scores for Candidate Keywords
        $aScoredKeywords = $this->generateCandidateKeywordScores($aCandidateKeywords, $aWordScores);
        if ($this->bDebug) { print_r($aScoredKeywords); }

        // Sort Keywords
        arsort($aScoredKeywords);
        if ($this->bDebug) { print_r($aScoredKeywords); }

        // Return
        $nKeywords = count($aScoredKeywords);
        if ($nMaxKeywordLimit > 0 && $nKeywords/3 > $nMaxKeywordLimit) {
            $nSlice = $nMaxKeywordLimit;
        } else {
            $nSlice = (int)($nKeywords / 3);
        }
        if ($this->bDebug) { print('Total # of keywords: ' . $nKeywords . "\n"); }

        return array_slice($aScoredKeywords, 0, $nSlice);
    }

    /**
    * Utility function to return a list of sentences from a string.
    * @param string $sText The text to split into sentences.
    */
    protected function splitSentences($sText)
    {
        return preg_split("/[.!?,;:\t\\-\\\"\\(\\)\\'\x{2019}\x{2013}]/u", $sText);
    }

    /**
    * Utility function to load stop words from a file and return as a list of words
    * @param string sStopWordFilePath Path and file name of the file containing the stop words.
    * @return array A list of stop words.
    */
    protected function loadStopWordsFromFile($sStopWordFilePath)
    {
        $aStopWords = array();

        if (($handle = @fopen($sStopWordFilePath, "rb")) === false) {
            throw new Exception("loadStopWordsFromFile() could not load file with path '".$sStopWordFilePath);
        }

        while (($sLine = fgets($handle)) !== false) {
            if ($sLine[0] != "#") {
                $aWords = explode(" ", $sLine);
                for ($i = count($aWords) - 1; $i >= 0; $i--) { // in case more than one per line
                    $aStopWords[] = strtolower(trim($aWords[$i]));
                }
            }
        }
        fclose($handle);
        return $aStopWords;
    }

    /**
    * Builds a RegEx pattern string from an array list with stopwords.
    * @param array $aStopWordList Wordlist with stopwords.
    * @return string RegEx pattern string for matching the provided words. Empty pattern if the passed array was empty.
    */
    protected function buildStopwordRegExPatternFromList($aStopWordList)
    {
        if (empty($aStopWordList)) { return "//"; }

        $aStopWordRegExList = array();
        for ($i = count($aStopWordList) - 1; $i >= 0; $i--) {
            $sWordRegEx = '\b' . $aStopWordList[$i] . '\b';
            $aStopWordRegExList[] = $sWordRegEx;
        }
        $sPattern = '/'.implode('|', $aStopWordRegExList).'/';
        return $sPattern;
    }

    /**
    * Generates list with candidate keywords.
    * @param array $aSentenceList A list with sentences to parse into keywords.
    * @param string $sStopWordPattern String with the StopWord RegEx pattern we're using to split the sentences.
    * @return array List with candidate keywords.
    */
    protected function generateCandidateKeywords($aSentenceList, $sStopWordPattern)
    {
        if (empty($aSentenceList)) { return array(); }
        if (empty($sStopWordPattern)) { return $aSentenceList; }

        $aPhraseList = array();

        for ($i = count($aSentenceList) - 1; $i >= 0; $i--) {
            $sTmp = preg_replace($sStopWordPattern, '|', strtolower(trim($aSentenceList[$i])));
            $aPhrases = explode('|', $sTmp);
            for ($j = count($aPhrases) - 1; $j >= 0; $j--) {
                $sPhrase = strtolower(trim($aPhrases[$j]));
                if ($sPhrase != "") {
                    $aPhraseList[] = $sPhrase;
                }
            }
        }
        return $aPhraseList;
    }

    /**
    * Utility function to calculate the scores of individual words.
    * @param array $aPhraseList List of phrases to calculate scores for.
    * @param integer $nMinWordCharacters Minimum number of characters for a word to be considered a keyword. 
    * @return array Hash of type phrase => score.
    */
    protected function calculateWordScores($aPhraseList, $nMinWordCharacters) {
        $aWordFrequency = array();
        $aWordDegree = array();
        for ($i = count($aPhraseList) - 1; $i >= 0; $i--) {
            $aWordList = $this->separatewords($aPhraseList[$i], $nMinWordCharacters);
            $nWordListLength = count($aWordList);
            $nWordListDegree = $nWordListLength - 1;
            for ($j = count($aWordList) - 1; $j >= 0; $j--) {
                $aWordFrequency[$aWordList[$j]] = isset($aWordFrequency[$aWordList[$j]]) ? $aWordFrequency[$aWordList[$j]] + 1 : 1;
                $aWordDegree[$aWordList[$j]] = isset($aWordDegree[$aWordList[$j]]) ? $aWordDegree[$aWordList[$j]] + $nWordListDegree : $nWordListDegree;
            }
        }
        foreach ($aWordFrequency as $sPhrase => $nFrequency) {
            $aWordDegree[$sPhrase] = $aWordDegree[$sPhrase] + $nFrequency;
        }

        // Calculate Word scores = deg(w)/frew(w)
        $aWordScores = array();
        foreach ($aWordFrequency as $sPhrase => $nFrequency) {
            $aWordScores[$sPhrase] = $aWordDegree[$sPhrase]/$nFrequency;
        }

        return $aWordScores;
    }

    /**
    * Utility function to return a list of all words that have a length greater than the specified number of characters.
    * @param string $sText The text to be split into words.
    * @param integer $nMinWordCharacters Minimum number of characters for a word to be considered a keyword.
    * @return array List of Words
    */
    protected function separateWords($sText, $nMinWordCharacters)
    {
        $sPattern = "/[^a-zA-Z0-9_\+\-]/";
        $aWords = array();
        $aSplit = preg_split($sPattern, $sText);
        for ($i = count($aSplit) - 1; $i >= 0; $i--) {
            $sCurrWord = strtolower(trim($aSplit[$i]));
            // leave numbers in phrase, but don't count as words, since they tend to inflate scores of their phrases
            if (strlen($aSplit[$i]) > $nMinWordCharacters && !empty($aSplit[$i]) && !is_numeric($aSplit[$i])) {
                $aWords[] = $aSplit[$i];
            }
        }
        return $aWords;
    }

    /**
    * Generates Keyword Scores for a list of Candidate Keywords
    * @param array $aCandidateKeywords List with Candidate Keywords
    * @param array $aWordScores Hash with Phrase Scores
    * @return array Hash of type keyword => score
    */
    protected function generateCandidateKeywordScores($aCandidateKeywords, $aWordScores) {
        $aScoredKeywords = array();
        for ($i = count($aCandidateKeywords) - 1; $i >= 0; $i--) {
            $aScoredKeywords[$aCandidateKeywords[$i]] = 0;
            $aWords = $this->separateWords($aCandidateKeywords[$i],0);
            $nKeywordScore = 0;
            for ($j = count($aWords) - 1; $j >= 0; $j--) {
                if (isset($aWordScores[$aWords[$j]])) { $nKeywordScore += $aWordScores[$aWords[$j]]; }
            }
            $aScoredKeywords[$aCandidateKeywords[$i]] = $nKeywordScore;
        }
        return $aScoredKeywords;
    }   
}

?>
