<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Search;
use AppBundle\Repository\SearchRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use AppBundle\Twig\Extension\Porter2Extension;

class SearchController extends Controller
{
	const CHAR_LENGTH = 2;

	protected $stopWords = array(
		'что', 'как', 'все', 'она', 'так', 'его', 'только', 'мне', 'было', 'вот',
		'меня', 'еще', 'нет', 'ему', 'теперь', 'когда', 'даже', 'вдруг', 'если',
		'уже', 'или', 'быть', 'был', 'него', 'вас', 'нибудь', 'опять', 'вам', 'ведь',
		'там', 'потом', 'себя', 'может', 'они', 'тут', 'где', 'есть', 'надо', 'ней',
		'для', 'тебя', 'чем', 'была', 'сам', 'чтоб', 'без', 'будто', 'чего', 'раз',
		'тоже', 'себе', 'под', 'будет', 'тогда', 'кто', 'этот', 'того', 'потому',
		'этого', 'какой', 'ним', 'этом', 'один', 'почти', 'мой', 'тем', 'чтобы',
		'нее', 'были', 'куда', 'зачем', 'всех', 'можно', 'при', 'два', 'другой',
		'хоть', 'после', 'над', 'больше', 'тот', 'через', 'эти', 'нас', 'про', 'них',
		'какая', 'много', 'разве', 'три', 'эту', 'моя', 'свою', 'этой', 'перед',
		'чуть', 'том', 'такой', 'более', 'всю'
	);

    /**
     * @Route("/search/", name="app_search_index")
     * @Route("/{_locale}/search/", name="app_search_index_locale")
     *
     * @param Request $request
     * @return Response
     */
    public function indexAction(Request $request)
    {
        $page = $request->query->get('page');

        $text = $request->query->get('searchword');

        $stext = strip_tags($text);
        $stext = mb_strtolower($stext); //для регистронезависимого поиска
        $stext = str_replace(array('&nbsp;','  '),' ', $stext);
        //$stext = str_replace('ё','е',$stext);// заменить ё на е
        //$stext = str_replace('й','и',$stext);// заменить й на и
        $symbols = array('&nbsp;','&laquo;','&raquo;','&mdash;','&thinsp;', '!','?','.',',','(',')','+','“','”',';','«','»');
        $stext = str_replace($symbols,'',$stext);

        $stext = preg_replace("|[\s]+|i"," ",$stext); // удалить лишние пробельные символы

        $error = $result = '';
        $count = $pageCount = $c = 0;
        $locale = $request->getLocale();

		$ws = explode(' ', $stext);
		$words = [];
		if($locale == 'en'){
			foreach($ws as $w){
				if (strlen($w) < 3 || in_array($w, $this->stopWords)) continue;
				if ($c++ > 4) break;
				$words[] = Porter2Extension::stem($w);
			}
		} else {
			foreach($ws as $w){
				if (strlen($w) < 3 || in_array($w, $this->stopWords)) continue;
				if ($c++ > 4) break;
				$words[] = $this->stem($w);
			}
		}

        if($stext!='' && strlen($stext) > 3 && count($words) > 0){
            $searchRepository = $this->getDoctrine()->getRepository(Search::class);

            $result = $searchRepository->findLatest($stext, $locale, $words);
            $count = count($result);

	        $pageCount = ceil(count($result) / SearchRepository::PER_PAGE);
    	    if (null !== $page) $result = $searchRepository->findPaginated($text, $locale, $page-1, $words);

            $arr = [];
            foreach($result as $key=>$one){
                //$content = strip_tags($one->getContentOriginal());
		$content = preg_replace('#<[^>]+>#', ' ', $one[0]->getContentOriginal());
                //$contentcrop = str_replace('ё','е',$content);// заменить ё на е
                //$contentcrop = str_replace('й','и',$contentcrop);// заменить й на и

				$str = strtok(wordwrap($content, 250, "...\n"), "\n");
				for($i=0; $i<count($words); $i++){
					$reg = "/(\s*[\S]+\s+){0,3}\w*(".$words[$i].")\w*(\s*[\S]+\s*){0,5}/iu";
					preg_match($reg, $content, $m);
					if(isset($m[0])){
						preg_match("/(".$words[$i].")/iu", $m[0], $match);
						$str = preg_replace("/(".$words[$i].")/iu", "".$match[0]."", $m[0]);
					} else {
						continue;
					}
				}

				//for catalogue
				$substrs = explode('###',$str);
				if(count($substrs)>1 ) $str = str_replace('###',' ',$str);

                $one[0]->setContent($str);
                $arr[$key] = $one[0]->getUrl();
            }
/*
            $tmp = [];
            foreach($arr as $a => $v){
                if ( in_array($v,$tmp) ){
                    unset($result[$a]);
                };
                $tmp[] = $v;
            }
*/
        }
        //if(count($stext) <= 3) $error = '';

        return $this->render('search/index.html.twig',[
            'text' => $text,
            'count' => $count,
            'result' => $result != '' ? array_slice($result, 0, SearchRepository::PER_PAGE) : '',
            'currentPage' => $page !== null ? $page : '1',
            'pageCount' => $pageCount
        ]);
    }

// https://www.exlab.net/dev/noindex-search.html	
	protected function stem($word){
		$a = $this->rv($word);
		return $a[0].$this->step4($this->step3($this->step2($this->step1($a[1]))));
	}

	protected function rv($word){
		$vowels = array('а','е','и','о','у','ы','э','ю','я');
		$flag = 0;
		$rv = $start='';
		for ($i=0; $i<strlen($word); $i+=self::CHAR_LENGTH){
			if ($flag == 1) $rv .= substr($word, $i, self::CHAR_LENGTH); else $start .= substr($word, $i, self::CHAR_LENGTH);
			if (array_search(substr($word,$i,self::CHAR_LENGTH), $vowels) !== FALSE) $flag = 1;
		}
		return array($start,$rv);
	}

	protected function step1($word){
		$perfective1 = array('в', 'вши', 'вшись');
		foreach ($perfective1 as $suffix) 
			if (substr($word, -(strlen($suffix))) == $suffix && (substr($word, -strlen($suffix) - self::CHAR_LENGTH, self::CHAR_LENGTH) == 'а' || substr($word, -strlen($suffix) - self::CHAR_LENGTH, self::CHAR_LENGTH) == 'я')) 
				return substr($word, 0, strlen($word)-strlen($suffix));

		$perfective2 = array('ив','ивши','ившись','ывши','ывшись');
		foreach ($perfective2 as $suffix) 
			if (substr($word, -(strlen($suffix))) == $suffix) 
				return substr($word, 0, strlen($word) - strlen($suffix));

		$reflexive = array('ся', 'сь');
		foreach ($reflexive as $suffix) 
			if (substr($word, -(strlen($suffix))) == $suffix) 
				$word = substr($word, 0, strlen($word) - strlen($suffix));

		$adjective = array('ее','ие','ые','ое','ими','ыми','ей','ий','ый','ой','ем','им','ым','ом','его','ого','ему','ому','их','ых','ую','юю','ая','яя','ою','ею');
		$participle2 = array('ем','нн','вш','ющ','щ');
		$participle1 = array('ивш','ывш','ующ');
		foreach ($adjective as $suffix) if (substr($word, -(strlen($suffix))) == $suffix){
			$word = substr($word, 0, strlen($word) - strlen($suffix));
			foreach ($participle1 as $suffix) 
				if (substr($word, -(strlen($suffix))) == $suffix && (substr($word, -strlen($suffix) - self::CHAR_LENGTH, self::CHAR_LENGTH) == 'а' || substr($word, -strlen($suffix) - self::CHAR_LENGTH, self::CHAR_LENGTH) == 'я')) 
					$word = substr($word, 0, strlen($word) - strlen($suffix));

			foreach ($participle2 as $suffix) 
				if (substr($word, -(strlen($suffix))) == $suffix) 
					$word = substr($word, 0, strlen($word) - strlen($suffix));

			return $word;
		}

		$verb1 = array('ла','на','ете','йте','ли','й','л','ем','н','ло','но','ет','ют','ны','ть','ешь','нно');
		foreach ($verb1 as $suffix) 
			if (substr($word, -(strlen($suffix))) == $suffix && (substr($word, -strlen($suffix) - self::CHAR_LENGTH, self::CHAR_LENGTH) == 'а' || substr($word, -strlen($suffix) - self::CHAR_LENGTH, self::CHAR_LENGTH) == 'я')) 
				return substr($word, 0, strlen($word) - strlen($suffix));

		$verb2 = array('ила','ыла','ена','ейте','уйте','ите','или','ыли','ей','уй','ил','ыл','им','ым','ен','ило','ыло','ено','ят','ует','уют','ит','ыт','ены','ить','ыть','ишь','ую','ю');
		foreach ($verb2 as $suffix) 
			if (substr($word, -(strlen($suffix))) == $suffix) 
				return substr($word, 0, strlen($word) - strlen($suffix));
   
		$noun = array('а','ев','ов','ие','ье','е','иями','ями','ами','еи','ии','и','ией','ей','ой','ий','й','иям','ям','ием','ем','ам','ом','о','у','ах','иях','ях','ы','ь','ию','ью','ю','ия','ья','я');
		foreach ($noun as $suffix) 
			if (substr($word, -(strlen($suffix))) == $suffix) 
				return substr($word, 0, strlen($word) - strlen($suffix));
   
		return $word;
	} 

	protected function step2($word){
		return substr($word, -self::CHAR_LENGTH, self::CHAR_LENGTH) == 'и' ? substr($word, 0, strlen($word) - self::CHAR_LENGTH) : $word;
	}

	protected function step3($word){
		$vowels = array('а','е','и','о','у','ы','э','ю','я');
		$flag = 0;
		$r1 = $r2 = '';
		for ($i=0; $i<strlen($word); $i+=self::CHAR_LENGTH){
			if ($flag==2) $r1 .= substr($word, $i, self::CHAR_LENGTH);
			if (array_search(substr($word, $i, self::CHAR_LENGTH), $vowels) !== FALSE) $flag = 1;
			if ($flag = 1 && array_search(substr($word, $i, self::CHAR_LENGTH), $vowels) === FALSE) $flag = 2;
		}
		
		$flag = 0;
		for ($i=0; $i<strlen($r1); $i+=self::CHAR_LENGTH){
			if ($flag == 2) $r2 .= substr($r1, $i, self::CHAR_LENGTH);
			if (array_search(substr($r1, $i, self::CHAR_LENGTH), $vowels) !== FALSE) $flag = 1;
			if ($flag = 1 && array_search(substr($r1, $i, self::CHAR_LENGTH), $vowels) === FALSE) $flag = 2;
		}
   
		$derivational = array('ост', 'ость');
		foreach ($derivational as $suffix) 
			if (substr($r2, -(strlen($suffix))) == $suffix) 
				$word = substr($word, 0, strlen($r2) - strlen($suffix));

		return $word;
	}

	protected function step4($word){
		if (substr($word, -self::CHAR_LENGTH * 2) == 'нн') $word = substr($word, 0, strlen($word) - self::CHAR_LENGTH);
		else {
			$superlative = array('ейш', 'ейше');
			foreach ($superlative as $suffix) 
				if (substr($word, -(strlen($suffix))) == $suffix) 
					$word = substr($word, 0, strlen($word) - strlen($suffix));

			if (substr($word, -self::CHAR_LENGTH * 2) == 'нн') $word = substr($word, 0, strlen($word) - self::CHAR_LENGTH);
		}

		if (substr($word, -self::CHAR_LENGTH, self::CHAR_LENGTH) == 'ь') $word = substr($word, 0, strlen($word) - self::CHAR_LENGTH);

		return $word;
	}

}
