<?php
  
  //�������� ��� ������������� ID � ������ ���������� ��� �������������� ����
  //� ���� ������� � ������� ��������� �� ���������� ���������, �� �� ������ ���������� �� �������� � ����
  $client_id = 'ff5ef026-e21e-4e93-a135-daacd99272ac';
  $client_secret = 'owtlyhQKESN3774)_=aCR3@';
  //������������� URL, � �������� ����� ���������� �� ������� �����������
  $authRequestUrl = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
  
  //������ ���� POST-������� � ���������� �� ������������ � ������������� JSON � ������ $deserializedRequestActivity
  //� ���� ������� � ������� ���� ������� �� ���������� ��������� req, ��� ������������� � ��������� Azure Functions
  //�� ������ �������� ���� ������� �� ������ php://input, ���� �� ����������� Azure Functions
  $request = file_get_contents(getenv('req')); 
  //$request = file_get_contents('php://input'); //���� �� ����������� Azure Functions
  $deserializedRequestActivity = json_decode($request, true);
  //���� $deserializedRequestActivity �������� ���� id, ������� �������� ������ ���������� � �������� ���������
  if(isset($deserializedRequestActivity['id']))
  {
    //������ �����, ������� ������ ������ ��� ����������� ������ �� ���������. ����� ����� �������� ����� POST-������ � oAuth ������� Microsoft.
    //� ��������� stream context ��� ������� ������ ������, ��� ��� ���������� �������� ���������, �� ������ ������������ CURL.
    $authRequestOptions = array(
        'http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query(
                array(
                    'grant_type' => 'client_credentials',
                    'client_id' => $client_id, //ID ����������
                    'client_secret' => $client_secret, //������ ����������
                    'scope' => 'https://graph.microsoft.com/.default'
                )
            )
        )
    );
    //������� ������������������ ���� stream context � ��������� �� ���� ������ � oAuth �������
    $authRequestContext  = stream_context_create($authRequestOptions);
    
    //������ ����� �� ������ � ������������� ��� � ������ $authData
    $authResult = file_get_contents($authRequestUrl, false, $authRequestContext);
    $authData = json_decode($authResult, true);
    //���� $authData �������� ���� access_token, ������� �������������� �������� � ���������� ���������
    if(isset($authData['access_token']))
    {
        //���������� ����� ��� ��������� �� ��������
        switch ((string)$deserializedRequestActivity['type']) {
            case 'message':
                //������� ����� ������ �� ��������� � ������, ���� ��� ��������� ��������� message
                $message = 'New message is received: ' . (string)$deserializedRequestActivity['text'];
                break;
            
            default:
                //� ���� ������� �� �� ����� ������������ ��� ������ ���� ���������, ������� ������ �������, ��� �� �� ������� �� ����� ���������� ������
                $message = 'Unknown type';
                break;
        }
        //��������� ������ $deserializedResponseActivity � ������� ������, ������� ����� ��������� � Microsoft Bot Framework 
        $deserializedResponseActivity = array(
            //�� �������� ������� ����������
            'type' => 'message',
             //����� ������ �� ���������
            'text' => $message,
            
            //�������, ��� ����� - ��� ������� �����
            'textFormat' => 'plain', 
            //������������� ������ ������
            'locale' => 'ru-RU', 
            //������������� ���������� ID ����������, � ��������� �������� �� ��������� (����� �� ���� id ��������� POST-������� � ����������)
            'replyToId' => (string)$deserializedRequestActivity['id'],  
            //�������� id � ��� ��������� ���� (����� �� ����� recipient->id � recipient->name ��������� POST-������� � ����������, �� ���� id � name, ������� ���� ���������� �������� ���������)
            'from' => array(
                'id' => (string)$deserializedRequestActivity['recipient']['id'], 
                'name' => (string)$deserializedRequestActivity['recipient']['name']
            ),
            //������������� id � ��� ��������� ����, � �������� ����������, �� �������� ��� �������� ��������� (����� �� ����� from->id � from->name ��������� POST-������� � ����������)
            'recipient' => array(
                'id' => (string)$deserializedRequestActivity['from']['id'],
                'name' => (string)$deserializedRequestActivity['from']['name']
            ),
            //������������� id ������, � ������� �� �������� (����� �� ���� conversation->id ��������� POST-������� � ����������)
            'conversation' => array(
                'id' => (string)$deserializedRequestActivity['conversation']['id'] 
            )
        );
        //��������� URL, ���� ��������� ����� �� ���������. �� ���� �� ���������� �� ���������� ��������� POST-������� � �������� ��������� �������:
        // https://{activity.serviceUrl}/v3/conversations/{activity.conversation.id}/activities/{activity.id}
        // ��� activity - ��� �������� POST-������� � ����������, ����������������� ����� � ������ $deserializedRequestActivity
        // {activity.serviceUrl} ������������ ����� rtrim ��� �� ��������� ��������� ����������� ����, ������ ��� ������ �� ����, � ������ ��� ���
        // {activity.id} ���������� ���������� ����� urlencode, ������ ��� � ��� ����������� ����������� �������, ������� ������ URL � ������ ��������� ������
        $responseActivityRequestUrl = rtrim($deserializedRequestActivity['serviceUrl'], '/') . '/v3/conversations/' . $deserializedResponseActivity['conversation']['id'] . '/activities/' . urlencode($deserializedResponseActivity['replyToId']);
        //������� POST-������ � Microsoft Bot Connector API, � ������� ��������� ����� �� �������� ���������
        //� ��������� stream context ��� ������� ������ ������, ��� ��� ���������� �������� ���������, �� ������ ������������ CURL.
        $responseActivityRequestOptions = array(
            'http' => array(
                //������������� � ��������� POST-������� ������ ��� ����������� ������, ��� ������ (token_type) � ��� ����� (access_token)
                'header'  => 'Authorization: ' . $authData['token_type'] . ' ' . $authData['access_token'] . "\r\nContent-type: application/json\r\n",
                'method'  => 'POST',
                //� ���� ������� ��������� ��������������� � JSON-������ ������ � ������� ������ $deserializedResponseActivity
                'content' => json_encode($deserializedResponseActivity)
            )
        );
        //������� stream context � ��������� �� ���� ������������������ ���� ������ � Microsoft Bot Connector API
        $responseActivityRequestContext  = stream_context_create($responseActivityRequestOptions);
        $responseActivityResult = file_get_contents($responseActivityRequestUrl, false, $responseActivityRequestContext);
        //����� � ����� STDOUT ��� � ��������� � ��������� ���������� ���������
        fwrite(STDOUT, 'New message is received: "' . (string)$deserializedRequestActivity['text'] . '"');
    }
    
  }
?>