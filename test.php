<?php
require_once("rake.php");

$text = "Compatibility of systems of linear constraints over the set of natural numbers. Criteria of compatibility of a system of linear Diophantine equations, strict inequations, and nonstrict inequations are considered. Upper bounds for components of a minimal set of solutions and algorithms of construction of minimal generating sets of solutions for all types of systems are given. These criteria and the corresponding algorithms for constructing a minimal supporting set of solutions can be used in solving all the considered types of systems and systems of mixed types.";

// $stoppath = "FoxStoplist.txt"; // Fox stoplist contains "numbers", so it will not find "natural numbers" like in Table 1.1
$stoppath = "SmartStoplist.txt"; // SMART stoplist misses some of the lower-scoring keywords in Figure 1.5, which means that the top 1/3 cuts off one of the 4.0 score words in Table 1.1

$rake = new RAKE;

$rake->setDebug(true);
$rake->setStopWordFilePath($stoppath);
$rake->setWordMinCharacters(0);
$rake->setMaxKeywordLimit(0);
print_r($rake->generateKeywords($text));

?>