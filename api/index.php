<?php

/**
 * Greenbubble - A Twilio SMS bot that uses Airtable as a database.
 * Licensed under the MIT License.
 * Follow the instructions in the README to set up this bot.
 * Created by @codybrom@mstdn.social
 */

header("Content-Type: application/xml");

//  Get the values of variables
$slackWebhookUrl = getenv("slackWebhookUrl");
$airtableApiKey = getenv("airtableApiKey");
$airtableBaseId = getenv("airtableBaseId");

// Read the incoming Twilio SMS webhook data
$twilioPayload = $_POST;
error_log(implode($twilioPayload));

$body = trim($twilioPayload["Body"]);
$fromNumber = $twilioPayload["From"];
$numMedia = isset($twilioPayload["NumMedia"]) ? (int) $twilioPayload["NumMedia"] : 0;

function sendAirtableRequest(string $method, string $url, ?array $data = null): array
{
    /**
     * Send a request to the Airtable API.
     * @param string $method HTTP method for the request.
     * @param string $url API endpoint URL.
     * @param array|null $data Data to send with the request.
     * @return array Decoded JSON response.
     */

    global $airtableApiKey;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($data) {
        $json = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $airtableApiKey,
        "Content-Type: application/json",
    ]);
    $result = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception("Error with Airtable request: " . $url . $error);
        error_log($error);

    }

    // Add this line to log the result
    // error_log("Airtable result: " . $url . $result);

    return json_decode($result, true);
}

function sendSlackMessage(array $data): void
{
    /**
     * Send a message to the Slack webhook.
     * @param array $data Data to send with the request.
     */

    global $slackWebhookUrl;

    $json = json_encode($data);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $slackWebhookUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
    ]);
    $result = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception("Error sending message to Slack: " . $error);
        error_log($error);
    }
}

try {
    
    // Check if the sender exists in the "Senders" table in Airtable
    $senderName = "";
    $urlFromNumber = urlencode($fromNumber);
    $getSendersUrl = "https://api.airtable.com/v0/$airtableBaseId/Senders?fields%5B%5D=Name&filterByFormula=%7BPhone+Number%7D+%3D+'$urlFromNumber'";
    $decodedSenderResult = sendAirtableRequest("GET", $getSendersUrl);
    if (isset($decodedSenderResult["records"]) && count($decodedSenderResult["records"]) > 0) {
        $sender = $decodedSenderResult["records"][0];
        $senderName = $sender["fields"]["Name"];
        $senderId = isset($sender["id"]) ? $sender["id"] : null;
    } else if (!$senderName) {
        // If the sender does not exist in the "Senders" table, create a new record
        $createSenderUrl = "https://api.airtable.com/v0/$airtableBaseId/Senders";
        $newSenderData = [
            "fields" => [
                "Name" => "Unknown Number (" . $fromNumber . ")",
                "Phone Number" => $fromNumber,
            ],
        ];

        $createSenderResult = sendAirtableRequest("POST", $createSenderUrl, $newSenderData);
        $senderName = "Unknown Number (" . $fromNumber . ")";

        error_log("Decoded sender result: " . json_encode($decodedSenderResult));
        error_log("Sender name: " . $senderName);

        $senderId = isset($sender["id"]) ? $sender["id"] : null;

    }

    // Save the incoming message to the "Responses" table in Airtable
    $saveToResponsesUrl = "https://api.airtable.com/v0/$airtableBaseId/Responses";
    $responseData = [
        "fields" => [
            "Sender" => $fromNumber,
            "Message" => $body,
        ],
    ];

    if ($senderId) { // Check if $senderId is not null
        $responseData["fields"]["Author"] = [$senderId]; // Add the sender's ID to the Author field
    }

    // Check if there are any images in the incoming message
    if ($numMedia > 0) {
        $attachments = [];
        for ($i = 0; $i < $numMedia; $i++) {
            $mediaUrl = $twilioPayload['MediaUrl' . $i];
            $attachments[] = [
                "url" => $mediaUrl,
            ];
        }
        $responseData["fields"]["Attachments"] = $attachments;
        $numPhotosMessage = "Thanks! I got your photo" . ($numMedia > 1 ? "s" : "") . ".";
    }

    sendAirtableRequest("POST", $saveToResponsesUrl, $responseData);

    // Check if the message contains any trigger keywords from the Airtable "Commands" table
    // If the trigger keywords are fetched successfully, check if the message contains any of them
    $url = "https://api.airtable.com/v0/$airtableBaseId/Commands";
    $decodedResult = sendAirtableRequest("GET", $url);
    if (isset($decodedResult["records"])) {
        $keywords = $decodedResult["records"];
        $bodyLower = strtolower($body);
        foreach ($keywords as $keyword) {
            if (
                isset($keyword["fields"]["Command"]) &&
                isset($keyword["fields"]["Response"])
            ) {
                $trigger = strtolower($keyword["fields"]["Command"]);
                if ($bodyLower == $trigger) {
                    if (isset($numPhotosMessage)) {
                        $message = $numPhotosMessage . "\n\n" . $keyword["fields"]["Response"];
                    } else {
                        $message = $keyword["fields"]["Response"];
                    }
                    break;
                }
            }
        }
    }
    if (!isset($message)) {
        // Modify the default message to include $numPhotosMessage
        $defaultMessage = "I can only respond to specific keywords. Try again.";
        if (isset($numPhotosMessage)) {
            $message = $numPhotosMessage . "\n\n" . $defaultMessage;
        } else {
            $message = $defaultMessage;
        }
    }    

    // Create the TwiML response to send a reply to the sender
    $twimlParts = [
        '<?xml version="1.0" encoding="UTF-8"?>',
        '<Response>',
        '    <Message to="' . $fromNumber . '">' . $message . '</Message>',
        '</Response>',
    ];
    $twiml = implode("\n", $twimlParts);

    // Send a message to the Slack webhook with the incoming message and number
    $slackData = [
        "blocks" => [
            [
                "type" => "header",
                "text" => [
                    "type" => "plain_text",
                    "text" => "New Message from $senderName"
                ],
            ],
            [
                "type" => "context",
                "elements" => [
                    [
                        "type" => "mrkdwn",
                        "text" => "They sent"
                    ],
                    [
                        "type" => "mrkdwn",
                        "text" => "`$body`"
                    ],
                ],
            ],
            [
                "type" => "divider"
            ],
            [
                "type" => "context",
                "elements" => [
                    [
                        "type" => "mrkdwn",
                        "text" => "I sent back"
                    ],
                    [
                        "type" => "mrkdwn",
                        "text" => "```$message```"
                    ],
                ],
            ],
        ],
    ];

    // If there are media attachments, include them in the Slack message
    if ($numMedia > 0) {
        $mediaBlocks = [];

        for ($i = 0; $i < $numMedia; $i++) {
            $mediaUrl = $twilioPayload['MediaUrl' . $i];
            $mediaBlock = [
                "type" => "image",
                "image_url" => $mediaUrl,
                "alt_text" => "Media Attachment",
            ];
            $mediaBlocks[] = $mediaBlock;
        }

        $slackData['blocks'] = array_merge($slackData['blocks'], $mediaBlocks);
    }

    sendSlackMessage($slackData);

    // Output the TwiML as XML
    echo $twiml;

} catch (Exception $e) {
    // Prepare Twilio POST data for Slack message
    $twilioPostData = "From: {$fromNumber}\nBody: {$body}";
    if ($numMedia > 0) {
        $twilioPostData .= "\nMedia URLs:";
        for ($i = 0; $i < $numMedia; $i++) {
            $mediaUrl = $twilioPayload['MediaUrl' . $i];
            $twilioPostData .= "\n- {$mediaUrl}";
        }
    }

    // Send an error message to Slack
    $errorSlackMessage = "An error occurred in the SMS script: " . $e->getMessage();
    $errorSlackData = [
        "blocks" => [
            [
                "type" => "header",
                "text" => [
                    "type" => "plain_text",
                    "text" => "Error in SMS Script"
                ],
            ],
            [
                "type" => "section",
                "text" => [
                    "type" => "mrkdwn",
                    "text" => "```$errorSlackMessage```"
                ],
            ],
            [
                "type" => "section",
                "text" => [
                    "type" => "mrkdwn",
                    "text" => "Twilio POST Data:\n```\n$twilioPostData\n```"
                ],
            ],
            [
                "type" => "actions",
                "elements" => [
                    [
                        "type" => "button",
                        "text" => [
                            "type" => "plain_text",
                            "text" => "Resend POST Data"
                        ],
                        "action_id" => "resend_post_data",
                        "value" => json_encode($twilioPayload), // Pass the POST data as a JSON-encoded string
                    ],
                ],
            ],
        ],
    ];

    // Call the sendSlackMessage function with the error data
    sendSlackMessage($errorSlackData);

    // You can either display an error message to the user or just send a default message
    $errorMessage = "An error occurred. Please try again later.";
    $errorTwimlParts = [
        '<?xml version="1.0" encoding="UTF-8"?>',
        '<Response>',
        '    <Message to="' . $fromNumber . '">' . $errorMessage . '</Message>',
        '</Response>',
    ];
    $errorTwiml = implode("\n", $errorTwimlParts);

    error_log($errorTwiml);
    echo $errorTwiml;
}