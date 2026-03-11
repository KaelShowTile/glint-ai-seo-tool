<?php

class Glint_AI_SEO_API
{

    public static function generate_seo($content, $title_meta, $desc_meta)
    {
        $api_key = Glint_AI_SEO_Settings::get_setting('api_key', '');
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'Gemini API Key is missing. Please configure it in ST AI SEO Settings.');
        }

        // Prepare prompt
        $prompt = "You are an expert SEO specialist. Your task is to generate a highly optimized SEO Title and SEO Description for a web page based on the content and metadata provided below.\n\n";
        $prompt .= "Guidelines:\n";
        $prompt .= "1. SEO Title must be under 60 characters, compelling, and include primary keywords.\n";
        $prompt .= "2. SEO Description must be under 160 characters, encourage click-through, and summarize the page perfectly. Avoid 'salesy' jargon\n";
        $prompt .= "3. The Keyword Merge: You must merge the primary [Color] + [Special Feature/Tech] + [Material] into a single descriptive phrase (e.g., 'emerald polished porcelain' or 'grey backlit porcelain slab').\n";
        $prompt .= "Adaptive Tone:\n";
        $prompt .= "If the product is large (e.g., 1200x2800), emphasize seamlessness and scale.\n";
        $prompt .= "If the product is backlit, emphasize translucency and atmosphere.\n";
        $prompt .= "If it is a standard tile, emphasize precision and texture.\n";
        $prompt .= "Output MUST be valid JSON only. DO NOT wrap the output in markdown code blocks like ```json. \n";
        $prompt .= "Expected JSON format:\n{ \"title\": \"Generated Title\", \"description\": \"Generated description\" }\n\n";

        if (!empty($title_meta)) {
            $prompt .= "------ METADATA FOR SEO TITLE ------\n";
            foreach ($title_meta as $key => $val) {
                $prompt .= sanitize_text_field($key) . ": " . wp_strip_all_tags(is_array($val) ? wp_json_encode($val) : $val) . "\n";
            }
            $prompt .= "\n";
        }

        if (!empty($desc_meta)) {
            $prompt .= "------ METADATA FOR SEO DESCRIPTION ------\n";
            foreach ($desc_meta as $key => $val) {
                $prompt .= sanitize_text_field($key) . ": " . wp_strip_all_tags(is_array($val) ? wp_json_encode($val) : $val) . "\n";
            }
            $prompt .= "\n";
        }

        $prompt .= "------ CONTENT ------\n";
        // Strip massive HTML tags to save tokens and improve processing speed
        $content_stripped = wp_strip_all_tags($content);
        // Limit content to ~4000 characters to ensure we don't blow up context size unnecessarily (Gemini flash can handle more, but speed matters)
        if (strlen($content_stripped) > 4000) {
            $content_stripped = substr($content_stripped, 0, 4000) . '...';
        }
        $prompt .= $content_stripped;

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $api_key;

        $body = array(
            "contents" => array(
                    array(
                    "parts" => array(
                            array("text" => $prompt)
                    )
                )
            ),
            "generationConfig" => array(
                "temperature" => 0.7,
                "maxOutputTokens" => 2048,
                "responseMimeType" => "application/json"
            )
        );

        $request_args = array(
            'body' => wp_json_encode($body),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 30,
            'data_format' => 'body'
        );

        $response = wp_remote_post($url, $request_args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body_resp = wp_remote_retrieve_body($response);
        $data = json_decode($body_resp, true);

        if ($status !== 200) {
            $err = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown Gemini API Error';
            return new WP_Error('api_error', 'API Error: ' . $err);
        }

        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $text = $data['candidates'][0]['content']['parts'][0]['text'];

            // Clean up potential markdown formatting that instructions might have missed
            $text = trim($text);
            if (strpos($text, '```json') === 0) {
                $text = substr($text, 7);
                $text = substr($text, 0, -3);
            }
            elseif (strpos($text, '```') === 0) {
                $text = substr($text, 3);
                $text = substr($text, 0, -3);
            }

            // Attempt standard decode first
            $result = json_decode(trim($text), true);

            // If it failed because of trailing truncation, let's try to forcefully extract and close it
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Manually parse strings out of the broken JSON
                $salvaged = array('title' => '', 'description' => '');

                // Extract Title
                if (preg_match('/"title"\s*:\s*"(.*?)"/u', $text, $matches)) {
                    $salvaged['title'] = $matches[1];
                }

                // Extract Description - grab everything from "description": " to the end, then trim off trailing unclosed quotes/data
                if (preg_match('/"description"\s*:\s*"(.*)/s', $text, $matches)) {
                    $raw_desc = $matches[1];
                    // Strip anything after the last complete sentence or word if it cuts off haphazardly
                    // We remove trailing quotes, newlines, or floating partial words
                    $clean_desc = preg_replace('/["\}\]]*$/', '', rtrim($raw_desc));
                    $salvaged['description'] = $clean_desc;
                }

                if (!empty($salvaged['title']) || !empty($salvaged['description'])) {
                    return $salvaged;
                }
            }

            if (is_array($result) && isset($result['title']) && isset($result['description'])) {
                return $result;
            }
            return new WP_Error('parse_error', 'Failed to parse AI output. Output was: ' . $text);
        }

        return new WP_Error('no_content', 'API returned no recognizable content structure.');
    }

    public static function generate_content($post_title, $meta_data, $prompt_template)
    {
        $api_key = Glint_AI_SEO_Settings::get_setting('api_key', '');
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'Gemini API Key is missing. Please configure it in ST AI SEO Settings.');
        }

        if (empty($prompt_template)) {
            return new WP_Error('no_prompt', 'Content generation prompt is empty. Please configure it in ST AI SEO Settings.');
        }

        // Prepare metadata string
        $metadata_string = "";
        if (!empty($meta_data)) {
            foreach ($meta_data as $key => $val) {
                $metadata_string .= sanitize_text_field($key) . ": " . wp_strip_all_tags(is_array($val) ? wp_json_encode($val) : $val) . "\n";
            }
        } else {
            $metadata_string = "No metadata provided.";
        }

        // Replace placeholders in the prompt template
        $prompt = str_replace('[post_title]', $post_title, $prompt_template);
        $prompt = str_replace('[metadata]', $metadata_string, $prompt);

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $api_key;

        $body = array(
            "contents" => array(
                array(
                    "parts" => array(
                        array("text" => $prompt)
                    )
                )
            ),
            "generationConfig" => array(
                "temperature" => 0.8,
                "maxOutputTokens" => 8192,
                // "responseMimeType" => "text/plain" // We expect markdown/html back
            )
        );

        $request_args = array(
            'body' => wp_json_encode($body),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 120, // Increased timeout for potentially long content generation
            'data_format' => 'body'
        );

        $response = wp_remote_post($url, $request_args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body_resp = wp_remote_retrieve_body($response);
        $data = json_decode($body_resp, true);

        if ($status !== 200) {
            $err = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown Gemini API Error';
            return new WP_Error('api_error', 'API Error: ' . $err);
        }

        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $text = $data['candidates'][0]['content']['parts'][0]['text'];
            // The response is expected to be markdown or plain text, which can be directly inserted.
            // No JSON parsing needed here.
            return $text;
        }

        return new WP_Error('no_content', 'API returned no recognizable content structure.');
    }
}
