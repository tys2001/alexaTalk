<?php
class AlexaBase
{
    private $responseObj;
    private $requestObj;
    
    public function __construct()
    {
		header("Content-Type: application/json;charset=UTF-8");
		$this->requestObj = json_decode(file_get_contents('php://input'));
		$this->responseObj = json_decode(
		'{
			"version": "1.0",
			"sessionAttributes": {},
			"response": {
				"outputSpeech": {
					"type": "SSML",
					"ssml": "<speak>でっていう</speak>"
				},
				"card": {
					"type": "Simple",
					"title": "Horoscope",
					"content": "でっていう"
				},
				"reprompt": {
					"outputSpeech": {
						"type": "SSML",
						"ssml": "<speak>でっていう？</speak>"
					}
				},
				"shouldEndSession": true
			}
		}'
		);
    }

	public function IsEntry()
	{
		return $this->requestObj->session->new;
	}
	
	public function GetIntentName()
	{
		return $this->requestObj->request->intent->name;
	}
	
	public function GetIntentSlots()
	{
		$slots = [];
		foreach($this->requestObj->request->intent->slots as $slot)
		{
			$slots[$slot->name] = $slot->value;
		}
		return $slots;
	}
		
	public function Tell($content)
	{
		$this->responseObj->response->outputSpeech->ssml = $content;
		$this->responseObj->response->shouldEndSession = true;
		$this->Commit();
	}

	public function Ask($content, $reprompt = null)
	{
		if(!$reprompt) $reprompt = $content;
		$this->responseObj->response->outputSpeech->ssml = $content;
		$this->responseObj->response->reprompt->outputSpeech->ssml = $reprompt;
		$this->responseObj->response->shouldEndSession = false;
		$this->Commit();
	}

	public function Card($title, $content)
	{
		$this->responseObj->response->card->title = $title;
		$this->responseObj->response->card->content = $content;
	}
	
	public function SetSession($key, $value)
	{
		$this->responseObj->sessionAttributes->$key = $value;
	}
	
	public function GetSession($key)
	{
		return $this->requestObj->session->attributes->$key;
	}

	public function Commit()
	{
		//$this->Card("リクエスト",json_encode($this->requestObj));
		echo(json_encode($this->responseObj));
		exit();
	}
	
	public function Talk($file)
	{
		$xml = simplexml_load_file($file);
		$states = "";
		
		$intent = $this->GetIntentName();
		$answer = (string)$xml->xpath("intent[@name='".$intent."']")[0];
		
		$askId = $this->GetSession("askId");
		if($askId)
		{
			$response = $xml->xpath("state[@id='".$askId."']/response[@answer='".$answer."']")[0];
			if(!$response) $response = $xml->xpath("state[@id='".$askId."']/response[@answer='other']")[0];
			$states .= (string)$response;
			$id = (string)$response["nextid"];
		}
		else $id = "init";
		
		while ($id)
		{
			$state = $xml->xpath("state[@id='".$id."']")[0];
			if(!$state) break;
			$states .= (string)$state->content;
			$nextid = (string)$state["nextid"];
			if(!$nextid) break;
			else if($nextid == "_end") break;
			else if($nextid == "_ask")
			{
				$this->SetSession("askId", $id);
				$this->Ask("<speak>".$states."</speak>", "<speak>".(string)$state->content."</speak>");
			}
			else if($nextid == "_continue") $id = $askId;
			else $id = $nextid;
		}
		$this->Tell("<speak>".$states."</speak>");
	}
	
	public function TalkNew($file)
	{
		$xml = simplexml_load_file($file);
		$tell = "";
		$ask = "";
		
		$intent = $this->GetIntentName();
		$askId = $this->GetSession("askId");
		
		$state = $xml->xpath("state[@intent='".$intent."']")[0];
		if(!$state && $askId)
		{
			$askState = $xml->xpath("state[@id='".$askId."']")[0];
			$state = $xml->xpath("state[@id='".$askId."']/state[@intent='".$intent."']")[0];
			if(!$state) $state = $xml->xpath("state[@id='".$askId."']/state[@intent='_else']")[0];
		}
		if(!$state)
		{
			$state = $xml->xpath("state[@id='_init']")[0];
		}
		
		while (true)
		{
			if($state->tell) $tell .= (string)$state->tell;
			if($state->ask)
			{
				$ask .= (string)$state->ask;
				$this->SetSession("askId", (string)$state["id"]);
				break;
			}
			else if($state->jump)
			{
				$jumpId = (string)$state->jump["id"];
				if($jumpId == "_reask")
				{
					$ask .= (string)$askState->ask;
					$this->SetSession("askId", $askId);
					break;
				}
				$state = $xml->xpath("state[@id='".$jumpId."']")[0];
			}
			else break;
		}
		if($ask) $this->Ask("<speak>".$tell.$ask."</speak>", "<speak>".$ask."</speak>");
		else $this->Tell("<speak>".$tell."</speak>");
	}
	
}
?>