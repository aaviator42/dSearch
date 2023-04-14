# dSearch
A simple but powerful PHP full-text search engine.  

`v2.0`: `2022-12-19`  
License: `AGPLv3`

See it in action [here](https://r.aavi.xyz/proj/dSearch/)!

## What is this?

I wanted to see if I could modify the algorithm from [Cha](https://github.com/aaviator42/Cha/) to perform full-text searches, and it turns out that it works pretty well. 

It's under 300 lines of commented PHP code and supports fuzzy searching, `-"excluding terms"` and `+"requiring terms"`.

Take, for example, the following query:

```
+beautiful -river morning nature bird sky
```

dSearch will return results from the index that (i) don't contain *river*, (ii) definitely contain *beautiful* and (iii) preferably contain *morning*, *nature*, *bird* and *sky*.

If a match confidence of less than 100% is specified, dSearch will attempt to find fuzzy matches for terms outside of quotes and return the strongest match for each term if one is found that's above the confidence thershold. The recommended value is `85%`. At 85% it will match slight typos and different spellings (like _color_ and _colour_) while keeping results reasonably precise. 

Results are order by descending number of matches found. Precedence: excluded > required > optional.

dSearch is ideal for blogs, wikis and knowledge bases.

## How does it work?

Simplified: it essentially converts each item in the search index into an array of words (tags), and similarly converts the search query to an array of keywords.  

Then the number of matches for each item in the search index can be found with:

```
match_score(query, item) = n(keywords(query) ∩ tags(item))
```

It's a bit more complicated than that though because I decided to add support for search operators and fuzziness. So now the search operation is more like:

```
for each item in index:
  if item contains any excluded_keywords
    match_score = 0
    skip to next item in index

  if item doesn't contain all required_keywords
    match_score = 0
    skip to next item in index
  else 
    match_score = n(required_keywords)
  
  for each non-required and non-excluded keyword in search_query
    if found (fuzzy) match for keyword in tags
      match_score += 1
```

## Example usage
**Code:**  

```php
<?php
require 'dSearch.php';

$index = array();

//load the first ten chapters of Frankenstein in the search index
for($i = 1; $i <= 10; $i++){
	$index["Chapter $i"] = file_get_contents('frankenstein/chapter' . $i . '.txt');
}

//search query
$query = "monster animal man death";

//confidence 85% to allow for typos and regional spelling differences
$confidence = 85;

$results = \dSearch\search($query, $index, $confidence);

print_r($results);
```

**Output:**

```php
Array
(
  [Chapter 9] => Array
    (
        [score] => 4
        [matches] => Array
          (
            [0] => monster
            [1] => animal
            [2] => man
            [3] => death
          )
    )

  [Chapter 4] => Array
      (
        [score] => 3
        [matches] => Array
          (
            [0] => animal
            [1] => man
            [2] => death
          )
      )

  [Chapter 5] => Array
    (
      [score] => 3
      [matches] => Array
        (
          [0] => monster
          [1] => many
          [2] => death
        )
    )

  [...]
)
```


## Query syntax:

<ul>
	<li><code>cat dog bee ant</code><br>
		- Will search the items in the search index for these words and will return results in descending order of word matches.
	</li>
	<br>
	<li><code>"cat dog"</code><br>
		<code>+cat +dog</code><br>
		<code>+"cat dog"</code><br>
		- Results must contain <em>cat</em> and <em>dog</em>.
	</li>
	<br>
	<li><code>-ant -bee</code><br>
		<code>-"ant bee"</code><br>
		- Results must not contain <em>ant</em> or <em>bee</em>.
	</li>
	<br>
	<li>Eg: <code>+"cat dog" -"ant bee" fish bird lion</code><br>
		- This will show results that contain <em>cat</em> and <em>dog</em>,
		don't contain <em>ant</em> and <em>bee</em>,
		and preferably contain <em>fish</em>, <em>bird</em> and <em>lion</em>.
	</li>
</ul>


## Functions
dSearch contains a single public function:

### `\dSearch\search($query, $index, $confidence = 100)`

Performs a search for a string `$query` in `$index`. If `$confidence` is 100, it performs an exact search. If it's less than 100, it performs a fuzzy search. Recommended value is `85`, but you should test different values to find the best confidence thershold for your use case.

Returns the results as an array, ordered from strongest match to weakest. Each result contains every required word, and no excluded words (see syntax above). If fuzzy search is on and no exact matches are found for a word, then dSearch will attempt to find the strongest fuzzy match.

The expected format of `$index` is an array of item names and content:

```php

$index = [
  "item1" => "Lorem ipsum dolor sit amet...",
  "item2" => "Consectetur adipiscing elit...",
  "item3" => "Sed do eiusmod tempor incididunt...",
  ...
  "item100" => "Excepteur sint occaecat cupidatat..."
];
```

The resultant array contains _match scores_ and a _list of matches_ for each item that had a non-zero number of word matches.

```php
Array
(
  [item1] => Array
    (
      [score] => 5
      [matches] => Array
        (
          [0] => lorem
          [1] => ipsum
          [2] => dolor
        )
    )

  [item2] => Array
    (
      [score] => 2
      [matches] => Array
        (
          [0] => lorem
          [2] => dolor
        )
    )

	[item3] => Array
    (
      [score] => 1
      [matches] => Array
        (
          [0] => ipsum
        )
    )

  [item4] => Array
    (
      [score] => 1
      [matches] => Array
        (
          [0] => dolor
        )
    )

)

```


## Requirements
* [Supported versions](https://www.php.net/supported-versions.php) of PHP. As of writing that's 8.0+. dSearch almost certainly works on older versions of PHP, but is not tested on those.
