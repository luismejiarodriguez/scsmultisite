<?php

namespace Drupal\augmentor_openai_gpt3\Plugin\Augmentor;

use Drupal\augmentor_openai_gpt3\OpenAiGPT3Base;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;

/**
 * OpenAI GPT3 augmentor plugin implementation.
 *
 * @Augmentor(
 *   id = "openai_gpt3_completions",
 *   label = @Translation("OpenAI GPT3 Completion"),
 *   description = @Translation("Given a prompt, the model will return one or
 *    more predicted completions, and can also return the probabilities of
 *    alternative tokens at each position."),
 * )
 */
class OpenAiGPT3Completions extends OpenAiGPT3Base {

  /**
   * Default model to use when we don't have access to the API.
   */
  const DEFAULT_MODEL = 'gpt-3.5-turbo-instruct';

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'engine' => NULL,
      'prompt' => NULL,
      'temperature' => NULL,
      'max_tokens' => NULL,
      'suffix' => NULL,
      'stream' => NULL,
      'logprobs' => NULL,
      'echo' => NULL,
      'stop' => NULL,
      'logit_bias' => NULL,
      'top_p' => NULL,
      'n' => NULL,
      'best_of' => NULL,
      'frequency_penalty' => NULL,
      'presence_penalty' => NULL,
      'user_tracking' => NULL,
      'chunker' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    if ($this->configuration['key']) {
      $form['model'] = [
        '#type' => 'select',
        '#title' => $this->t('Model'),
        '#options' => $this->models(),
        '#default_value' => $this->configuration['engine'] ?? self::DEFAULT_MODEL,
      ];
    }

    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt'),
      '#default_value' => $this->configuration['prompt'] ?? '{input}',
      '#description' => $this->t('The prompt(s) to generate completions for, encoded as a string, array of strings, array of tokens, or array of token arrays.
        Note that <|endoftext|> is the document separator that the model sees during training, so if a prompt is not specified the model will generate as if from the beginning of a new document.'),
    ];

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => t('Advanced settings'),
      '#description' => t('See https://beta.openai.com/docs/api-reference/completions for more information.'),
    ];

    $form['advanced']['suffix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Suffix'),
      '#default_value' => $this->configuration['suffix'],
      '#description' => $this->t('The suffix that comes after a completion of inserted text.'),
    ];

    $form['advanced']['temperature'] = [
      '#type' => 'number',
      '#step' => '.01',
      '#title' => $this->t('Temperature'),
      '#default_value' => $this->configuration['temperature'] ?? 0,
      '#description' => $this->t('What sampling temperature to use. Higher values means the model will take more risks. Try 0.9 for more creative applications, and 0 (argmax sampling) for ones with a well-defined answer.
        We generally recommend altering this or top_p but not both.'),
    ];

    $form['advanced']['max_tokens'] = [
      '#type' => 'number',
      '#title' => $this->t('Max Tokens'),
      '#default_value' => $this->configuration['max_tokens'] ?? 16,
      '#description' => $this->t("The maximum number of tokens to generate in the completion.
        The token count of your prompt plus max_tokens cannot exceed the model's context length. Most models have a context length of 2048 tokens (except for the newest models, which support 4096)."),
    ];

    $form['advanced']['top_p'] = [
      '#type' => 'number',
      '#step' => '.01',
      '#title' => $this->t('Top P'),
      '#default_value' => $this->configuration['top_p'] ?? 0,
      '#description' => $this->t('An alternative to sampling with temperature, called nucleus sampling, where the model considers the results of the tokens with top_p probability mass. So 0.1 means only the tokens comprising the top 10% probability mass are considered.
        We generally recommend altering this or temperature but not both.'),
    ];

    $form['advanced']['n'] = [
      '#type' => 'number',
      '#title' => $this->t('N'),
      '#default_value' => $this->configuration['n'] ?? 1,
      '#description' => $this->t('How many completions to generate for each prompt.
        Note: Because this parameter generates many completions, it can quickly consume your token quota. Use carefully and ensure that you have reasonable settings for max_tokens and stop.'),
    ];

    $form['advanced']['best_of'] = [
      '#type' => 'number',
      '#title' => $this->t('Best Of'),
      '#default_value' => $this->configuration['best_of'] ?? 1,
      '#description' => $this->t('Generates best_of completions server-side and returns the "best" (the one with the highest log probability per token). Results cannot be streamed.
        When used with n, best_of controls the number of candidate completions and n specifies how many to return – best_of must be greater than n.
        Note: Because this parameter generates many completions, it can quickly consume your token quota. Use carefully and ensure that you have reasonable settings for max_tokens and stop.'),
    ];

    $form['advanced']['stream'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Stream'),
      '#default_value' => $this->configuration['stream'] ?? FALSE,
      '#description' => $this->t("Whether to stream back partial progress. If set, tokens will be sent as data-only server-sent events as they become available, with the stream terminated by a data: [DONE] message."),
    ];

    $form['advanced']['logprobs'] = [
      '#type' => 'number',
      '#title' => $this->t('Logprobs'),
      '#default_value' => $this->configuration['logprobs'],
      '#description' => $this->t("Include the log probabilities on the logprobs most likely tokens, as well the chosen tokens. For example, if logprobs is 5, the API will return a list of the 5 most likely tokens. The API will always return the logprob of the sampled token, so there may be up to logprobs+1 elements in the response.
        The maximum value for logprobs is 5. If you need more than this, please contact support@openai.com and describe your use case."),
    ];

    $form['advanced']['echo'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Echo'),
      '#default_value' => $this->configuration['echo'] ?? FALSE,
      '#description' => $this->t("Echo back the prompt in addition to the completion"),
    ];

    $form['advanced']['stop'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Stop'),
      '#default_value' => $this->configuration['stop'],
      '#description' => $this->t("Up to 4 sequences where the API will stop generating further tokens. The returned text will not contain the stop sequence."),
    ];

    $form['advanced']['logit_bias'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Logit Bias'),
      '#default_value' => $this->configuration['logit_bias'],
      '#description' => $this->t("Modify the likelihood of specified tokens appearing in the completion.
        Accepts a json object that maps tokens (specified by their token ID in the GPT tokenizer) to an associated bias value from -100 to 100. You can use this tokenizer tool (which works for both GPT-2 and GPT-3) to convert text to token IDs. Mathematically, the bias is added to the logit's generated by the model prior to sampling. The exact effect will vary per model, but values between -1 and 1 should decrease or increase likelihood of selection; values like -100 or 100 should result in a ban or exclusive selection of the relevant token.
        As an example, you can pass {'50256': -100} to prevent the <|endoftext|> token from being generated."),
    ];

    $form['advanced']['frequency_penalty'] = [
      '#type' => 'number',
      '#step' => '.01',
      '#title' => $this->t('Frequency Penalty'),
      '#default_value' => $this->configuration['frequency_penalty'] ?? 0,
      '#description' => $this->t("Number between -2.0 and 2.0. Positive values penalize new tokens based on their existing frequency in the text so far, decreasing the model's likelihood to repeat the same line verbatim."),
    ];

    $form['advanced']['presence_penalty'] = [
      '#type' => 'number',
      '#step' => '.01',
      '#title' => $this->t('Presence Penalty'),
      '#default_value' => $this->configuration['presence_penalty'] ?? 0,
      '#description' => $this->t("Number between -2.0 and 2.0. Positive values penalize new tokens based on whether they appear in the text so far, increasing the model's likelihood to talk about new topics."),
    ];

    $form['advanced']['user_tracking'] = [
      '#type' => 'checkbox',
      '#default_value' => $this->configuration['user_tracking'] ?? TRUE,
      '#description' => $this->t('Enable a unique identifier representing your end-user, which will help OpenAI to monitor and detect abuse.'),
      '#title' => t('User Tracking'),
    ];

    $chunker = $this->configuration['chunker'];

    $form['chunker'] = [
      '#type' => 'details',
      '#title' => $this->t('Summarizer'),
    ];

    $form['chunker']['description'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Breaks the input into chunks and summarize them one-at-a-time to join them again.'),
    ];

    $form['chunker']['enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Summarizer'),
      '#default_value' => $chunker['enable'] ?? FALSE,
    ];

    if ($this->configuration['key']) {
      $form['chunker']['engine'] = [
        '#type' => 'select',
        '#title' => $this->t('Model'),
        '#options' => $this->models(),
        '#default_value' => $chunker['engine'] ?? self::DEFAULT_MODEL,
        '#states' => [
          'visible' => [
            ':input[name="settings[chunker][enable]"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }

    $form['chunker']['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt'),
      '#default_value' => $chunker['prompt'] ?? 'Summarize the following text: {input}',
      '#description' => $this->t('The prompt used to summarize the different chunks.'),
      '#states' => [
        'visible' => [
          ':input[name="settings[chunker][enable]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['chunker']['min'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum length'),
      '#description' => $this->t('The minimum length of the processed input line in characters.'),
      '#default_value' => $chunker['min'] ?? 4000,
      '#states' => [
        'visible' => [
          ':input[name="settings[chunker][enable]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['chunker']['max'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum length'),
      '#description' => $this->t('The maximum length of the processed input line in characters.'),
      '#default_value' => $chunker['max'] ?? 6000,
      '#states' => [
        'visible' => [
          ':input[name="settings[chunker][enable]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $advance_settings = $form_state->getValue('advanced');
    $this->configuration['engine'] = $form_state->getValue('model');
    $this->configuration['prompt'] = $form_state->getValue('prompt');
    $this->configuration['temperature'] = $advance_settings['temperature'];
    $this->configuration['max_tokens'] = $advance_settings['max_tokens'];
    $this->configuration['top_p'] = $advance_settings['top_p'];
    $this->configuration['n'] = $advance_settings['n'];
    $this->configuration['best_of'] = $advance_settings['best_of'];
    $this->configuration['suffix'] = $advance_settings['suffix'];
    $this->configuration['stream'] = $advance_settings['stream'];
    $this->configuration['logprobs'] = $advance_settings['logprobs'];
    $this->configuration['echo'] = $advance_settings['echo'];
    $this->configuration['stop'] = $advance_settings['stop'];
    $this->configuration['logit_bias'] = $advance_settings['logit_bias'];
    $this->configuration['frequency_penalty'] = $advance_settings['frequency_penalty'];
    $this->configuration['presence_penalty'] = $advance_settings['presence_penalty'];
    $this->configuration['user_tracking'] = $advance_settings['user_tracking'];
    $this->configuration['chunker'] = $form_state->getValue('chunker');
  }

  /**
   * Creates a completion for the provided prompt and parameters.
   *
   * @param string $input
   *   The text to use as source for the completion manipulation.
   *
   * @return array
   *   The completion output.
   */
  public function execute($input) {
    $output = [];
    $input = $this->summarizeInput($input);
    $completion_max_tokens = (int) $this->configuration['max_tokens'];
    $completion_max_input_length = 0.9 * $completion_max_tokens * 4;

    // If the resulting input is too long, chunk it again.
    if (strlen($input) > $completion_max_input_length) {
      $input = $this->summarizeInput($input);
    }

    $options = [
      'model' => $this->configuration['engine'],
      'prompt' => str_replace('{input}', $input, $this->configuration['prompt']),
      'temperature' => (double) $this->configuration['temperature'],
      'max_tokens' => $completion_max_tokens,
      'top_p' => (double) $this->configuration['top_p'],
      'n' => (int) $this->configuration['n'],
      'best_of' => (int) $this->configuration['best_of'],
      'frequency_penalty' => (double) $this->configuration['frequency_penalty'],
      'presence_penalty' => (double) $this->configuration['presence_penalty'],
    ];

    if ($this->configuration['user_tracking']) {
      $options['user'] = $this->currentUser->id();
    }
    // Get the completion results into the default key using the given options.
    $output['default'] = $this->completion($options);

    return $output;
  }

  /**
   * Summarizes the given input text.
   *
   * @param string $input
   *   The text to use as source for the completion manipulation.
   *
   * @return string
   *   The summarized input.
   */
  private function summarizeInput($input) {
    $chunker_settings = $this->configuration['chunker'];

    if ($chunker_settings && $chunker_settings['enable']) {
      $summarized_input = '';
      $merged_chunk = '';

      $chunks = $this->stringToChunks(
        $input,
        $chunker_settings['min'],
        $chunker_settings['max'],
      );

      foreach ($chunks as $chunk) {
        // Skip tiny chunks.
        if (strlen(trim($chunk)) <= 20) {
          continue;
        }

        $merged_chunk .= trim($chunk);

        // Keep merging chunks until we reach the minimum length.
        if (strlen($merged_chunk) < $chunker_settings['min']) {
          continue;
        }

        $options = [
          'model' => $chunker_settings['engine'] ?? self::DEFAULT_MODEL,
          'prompt' => str_replace('{input}', $merged_chunk, $chunker_settings['prompt']),
          'temperature' => 0.7,
          'max_tokens' => 2048,
          'top_p' => 1.0,
          'n' => 1,
          'best_of' => 1,
          'frequency_penalty' => 0.0,
          'presence_penalty' => 0.0,
        ];

        // Get the completion results so we can get the best summary.
        if ($choices = $this->completion($options)) {
          $best_choice = $choices[0];
          $summarized_input .= $best_choice;
        }

        // Reset the merged chunk.
        $merged_chunk = '';
      }

      if ($summarized_input) {
        $input = $summarized_input;
      }
    }

    return $input;
  }

  /**
   * Gets the available list of OpenAI models.
   *
   * @return array
   *   A list of available models.
   */
  private function models() {
    $models = [];
    if ($this->getSdk() === 'orhanerday') {
      $response = $this->getClient()->listModels();
      $results = Json::decode($response, TRUE);
    }
    else {
      $response = $this->getClient()->models()->list();
      $results = $response->toArray();
    }
    foreach ($results['data'] as $result) {
      $models[$result['id']] = $result['id'];
    }
    return $models;
  }

  /**
   * Performs a completion request to the OpenAI API using the selected SDK.
   *
   * @param array $options
   *   List of options to pass to the completion request.
   *
   * @return array
   *   A list of choices from the completion response.
   */
  private function completion(array $options) {
    $choices = [];

    try {
      $error_message = FALSE;

      if ($this->getSdk() === 'orhanerday') {
        $response = $this->getClient()->completion($options);
        $result = Json::decode($response, TRUE);
      }
      else {
        $response = $this->getClient()->completions()->create($options);
        $result = $response->toArray();
      }

      if (array_key_exists('_errors', $result)) {
        $error_message = $result['_errors']['message'];
      }
      elseif (array_key_exists('error', $result)) {
        $error_message = $result['error']['message'];
      }

      if ($error_message) {
        $this->logger->error('OpenAI API error: %message.', [
          '%message' => $error_message,
        ]);

        return [
          '_errors' => $this->t('Error during the completion execution, please check the logs for more information.')->render(),
        ];
      }

      if (array_key_exists('choices', $result)) {
        foreach ($result['choices'] as $choice) {
          $choices[] = $this->normalizeText($choice['text']);
        }
      }
    }
    catch (\Throwable $error) {
      $this->logger->error('OpenAI API error: %message.', [
        '%message' => $error->getMessage(),
      ]);
      return [
        '_errors' => $this->t('Error during the completion execution, please check the logs for more information.')->render(),
      ];
    }

    return $choices;
  }

}
