<?php
/*
dSearch.php
v2.0 - 2022-12-19

By @aaviator42
License: AGPLv3
*/

namespace dSearch;

function search($query, $index, $confidence = 100){
	//convert query string into arrays
	$inputArrays = parseInput($query);
	
	//words the MUST be in the results
	$requiredWords = $inputArrays["requiredWords"];
	
	//words that MUST NOT be in the results
	$excludedWords = $inputArrays["excludedWords"];
	
	//words that SHOULD be in the results
	$optionalWords = $inputArrays["optionalWords"];
	
	//convert each search index item's text into an arrays of tags
	foreach($index as $name => $content){
		$index[$name] = stringToArray($content);
	}
	
	//array to contain match scores of each result
	$scores = array();
	//array to contain the matches of each result
	$matches = array();
	
	//no fuzziness
	if($confidence > 100){
		$confidence = 100;
	}
	
	//min fuzziness
	if($confidence < 0){
		$confidence = 0;
	}
	
	//compare every word from query string with every word in every index item
	//loop through each index item
	
	//run loop for each search index item
	foreach($index as $item => $tags){
		
		if(count(array_intersect($tags, $requiredWords)) < count($requiredWords)){
			//item's tags do not contain all required words
			//so we move on to the next item
			continue;
		} else {
			//remove required words from the item's tags
			//so we don't search through them again
			$tags = array_diff($tags, $requiredWords);
		}
		
		if(count(array_intersect($tags, $excludedWords)) > 0){
			//item's tags contain ignore words
			//so we move on to the next item
			continue;
		}
		
		//if execution has reached here, that means that the item's tags
		//contain ALL required words, and NO excluded words.
		
		$matches[$item] = [];
		
		//add required terms to the array of matches
		//and update the match score accordingly
		foreach($requiredWords as $word){
			$matches[$item][] = $word;
		}
		$scores[$item] = sizeof($requiredWords);
		
		//loop through every word in query string
		foreach($optionalWords as $qWord){
			
			//variable to store measured similarity from each loop iteration
			$currentMeasuredSimilarity;
			
			//variable to store the maximum measured similarity
			$maxMeasuredSimilarity = -1;
			
			//variable to store the previous match that crossed the confidence threshold
			$prevMatch = NULL;
			
			//loop through every tag for the current item
			foreach($tags as $tag){
				//compare similarity of tag and query word
				similar_text($tag, $qWord, $currentMeasuredSimilarity);
				
				if($currentMeasuredSimilarity >= $confidence){
					//similarity is greater than or equal to the confidence threshold
					
					if($currentMeasuredSimilarity > $maxMeasuredSimilarity){						
						//similarity is greater than the similarity of previous matches found for this word
						$maxMeasuredSimilarity = $currentMeasuredSimilarity;
						//increase match score
						$scores[$item]++;
						
						$intersect = count(array_intersect($matches[$item], [$prevMatch]));
						if($intersect > 0){
							//remove the previous (weaker) match from the matches array
							$matches[$item] = array_diff($matches[$item], [$prevMatch]);
							$scores[$item]--;
						}
						
						//add the new match to the matches array
						$matches[$item][] = $tag;
						//store new match in $prevMatch for next iteration's comparison
						$prevMatch = $tag;

						if($currentMeasuredSimilarity == 100){
							//if an exact match was found, move on to the next query word
							continue 2;
						}
					}
				}
			}
		}
	}
	
	//sort scores in descending order
	arsort($scores);
	
	//array to store results in
	$results = array();
	
	//generate results array
	foreach($scores as $item => $score){
		if($score > 0){
			//skip items with no matches
			$results[$item] = [	
				'score' => $score, 
				'matches' => $matches[$item]
			];
		}
	}
	
	//search complete!
	return $results;
}


//-------
function stringToArray($qString){
	
	//if passed an array, return it unmodified
	if(is_array($qString)){
		return $qString;
	}
	
	//string to lowercase
	$qString = strtolower($qString);
	
	//strip whitespace
	$qString = preg_replace('!\s+!', ' ', $qString);
	
	//strip punctuation
	$qString = preg_replace("#[[:punct:]]#", "", $qString);

	//convert string to array
	$qArray = explode(" ", $qString);
	
	//remove empty elements
	$qArray = array_filter($qArray, fn($value) => !is_null($value) && $value !== '');
	
	//return array_values($qArray);
	return array_unique(array_values($qArray));
}


function parseInput($input) {
	//Initialize arrays to store the phrases
	//These are for phrases from the search query like:
	// "cat dog man" (required) or
	// +"ant tree sea" (required) or
	// -"cow goat river" (excluded)
    $requiredPhrases = array();
    $excludedPhrases = array();
	
	//Initialize arrays to store the words
	//This is for words that aren't in a phrase 
	$otherWords = array();
	//This is for all required words
	$requiredWords = array();
	//This is for all excluded words
	$excludedWords = array();
	//This is for all optional words
	$optionalWords = array();
	
	//Convert input to lowercase
	$input = strtolower($input);
	
	//Remove all whitespace
	$input = preg_replace('/\s+/', ' ', $input);
	
	//Remove all punctuation except plus signs, hyphens, apostrophes, and spaces
	$input = preg_replace("/[^\w\s'\"+-]+/", "", $input);
	
    //Split the input string into an array of phrases and words
	preg_match_all('/((?<=\s|^)(\+|-)"[^"]*"|"[^"]*"|\S+)/', $input, $matches);
    $phrasesAndWords = $matches[0];
	
    //Loop through each phrase or word
    foreach ($phrasesAndWords as $phraseOrWord) {
        //Check if the phrase or word is a required phrase 
		//(enclosed in quotes and prefixed by a plus sign)
        if (preg_match('/^\+"(.+)"$/', $phraseOrWord, $matches)) {
            $requiredPhrases[] = $matches[1];
        }
        //Check if the phrase or word is an excluded phrase 
		//(enclosed in quotes and prefixed by a minus sign)
        else if (preg_match('/^-"(.+)"$/', $phraseOrWord, $matches)) {
            $excludedPhrases[] = $matches[1];
        }
        //Check if the phrase or word is a required phrase 
		//(enclosed in quotes)
        else if (preg_match('/^"(.+)"$/', $phraseOrWord, $matches)) {
            $requiredPhrases[] = $matches[1];
        }
        //words outside of quotes are optional
        else {
            $otherWords[] = $phraseOrWord;
        }
    }

	//parse $otherWords:
	
	//Loop through the words
	foreach ($otherWords as $word) {
		//Check if the word is prefixed by + or -
		if (substr($word, 0, 1) == "+") {
			//Add the word to the requiredWords array
			$requiredWords[] = substr($word, 1);
		} elseif (substr($word, 0, 1) == "-") {
			//Add the word to the excludedWords array
			$excludedWords[] = substr($word, 1);
		} else if (preg_match('/^\S+$/', $word)) {
			//Add the word to the optionalWords array
            $optionalWords[] = $word;
        }
	}
	
	//Loop through the strings in $requiredPhrases
	foreach ($requiredPhrases as $phrase) {
		//Split the string into an array of words
		$words = explode(" ", $phrase);

		//Add the words to the requiredWords array
		$requiredWords = array_merge($requiredWords, $words);
	}
	
	//Loop through the strings in $excludedPhrases
	foreach ($excludedPhrases as $phrase) {
		//Split the string into an array of words
		$words = explode(" ", $phrase);

		//Add the words to the excludedWords array
		$excludedWords = array_merge($excludedWords, $words);
	}
	
	//remove duplicates from word arrays
	$optionalWords = array_unique($optionalWords);
	$requiredWords = array_unique($requiredWords);
	$excludedWords = array_unique($excludedWords);
	
	//remove non-optional words from $optionalWords
	$optionalWords = array_diff($optionalWords, $excludedWords, $requiredWords);
	
	//remove excluded words from $requiredWords
	$requiredWords = array_diff($requiredWords, $excludedWords);
	
	return array(
        'optionalWords' => $optionalWords,
        'requiredWords' => $requiredWords,
        'excludedWords' => $excludedWords
    );
}
