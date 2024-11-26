<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AIController extends Controller
    
{
    public function ask_ai_stream(Request $request) {
        try {
            $response = new StreamedResponse(function() use ($request) {
                $thread_id = OpenAI::threads()->create([])->toArray()['id'];
                $response = OpenAI::threads()->messages()->create($thread_id, [
                    'role' => 'user',
                    'content' => [
                        [
                            "type" => "text",
                            "text" => $request->get('prompt')
                        ],
                    ],
                ]);

                $stream = OpenAI::threads()->runs()->createStreamed(
                    threadId: $thread_id,
                    parameters: [
                        'assistant_id' => $request->get('assistant_id'),
                    ],
                );

                $run = [];
                $content = '';
                do{
                    foreach($stream as $response){
                
                        switch($response->event){
                            case 'thread.run.created':
                            case 'thread.run.queued':
                            case 'thread.run.cancelling':
                                $run = $response->response;
        
                                $message = json_encode([
                                    "data_stream_event" => $response->event,
                                ]);
                                echo "data: $message\n\n";
                                flush();
        
                                break;
                            case 'thread.run.completed':
                                $run = $response->response;
        
                                $message = json_encode([
                                    "data_stream_event" => $response->event,
                                ]);
                                echo "data: $message\n\n";
                                flush();
                                $message = json_encode([
                                    "final_response" => $content,
                                ]);
                                echo "data: $message\n\n";
                                ob_flush();
                                flush();
                                break;
                            case 'thread.run.expired':
                            case 'thread.run.cancelled':
                            case 'thread.run.failed':
                                $run = $response->response;
                                break 3;
                            case 'thread.run.requires_action':
                                $run = $response->response;
                                $tool_call_id = $response->response->requiredAction->submitToolOutputs->toolCalls[0]['id'];
                                $stream = OpenAI::threads()->runs()->submitToolOutputsStreamed(
                                    threadId: $run->threadId,
                                    runId: $run->id,
                                    parameters: [
                                        'tool_outputs' => [
                                            [
                                                'tool_call_id' => $tool_call_id,
                                                'output' => 'Error.',
                                            ]
                                        ],
                                    ]
                                );
                                break;
                            case 'thread.message.delta':
                                $content .= $response->response['delta']['content'][0]['text']['value'];
                                echo "data: " . json_encode(['message' => $response->response['delta']['content'][0]['text']['value']]) . "\n\n";
                                ob_flush();
                                flush();
                                break;
        
                        }
                    }
                } while ($run->status != "completed");
            });

            $response->headers->set('Content-Type', 'text/event-stream');
            $response->headers->set('Cache-Control', 'no-cache');
    
            return $response;
        } catch(\Exception $error) {
            logger($error);
            return response()->json(['error' => $error->getMessage()], 500);
        }
    }
    
    public function ask_ai_no_thread(Request $request) {
        try {
            $messages = $request->messages;
            logger($messages);
            $response = OpenAI::chat()->create([
                'model' => 'gpt-3.5-turbo-1106',
                'messages' => $messages
            ]);
            $content = $response->choices[0]->message->content;
            return response()->json(['response' => $content]);
        } catch(\Exception $error) {
            return response()->json(['error' => $error->getMessage()], 500);
        }
    }
}
