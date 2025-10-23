<?php

namespace Drupal\augmentor_openai_gpt3;

use Drupal\augmentor\AugmentorBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use OpenAI\Client;
use Orhanerday\OpenAi\OpenAi as OrhanerdayOpenAi;

/**
 * OpenAI GPT3 augmentor plugin implementation.
 */
/**
 * Provides a base class for OpenAI GPT3 augmentors.
 *
 * @see \Drupal\augmentor\Annotation\Augmentor
 * @see \Drupal\augmentor\AugmentorInterface
 * @see \Drupal\augmentor\AugmentorManager
 * @see \Drupal\augmentor\AugmentorBase
 * @see plugin_api
 */
class OpenAiGPT3Base extends AugmentorBase implements ContainerFactoryPluginInterface {

  /**
   * The OpenAI SDK API client.
   *
   * @var \Orhanerday\OpenAi\OpenAi
   */
  protected $client;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'sdk' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['sdk'] = [
      '#type' => 'select',
      '#title' => $this->t('SDK to use'),
      '#options' => [
        // phpcs:ignore DrupalPractice.General.OptionsT.TforValue
        'orhanerday' => 'orhanerday/open-ai',
        // phpcs:ignore DrupalPractice.General.OptionsT.TforValue
        'openai_php' => 'openai-php/client',
      ],
      '#description' => $this->t('Choose the OpenAI PHP SDK to use:
        <a href=":link">orhanerday/open-ai</a> or <a href=":link2">openai-php/client</a>', [
          ':link' => 'https://github.com/orhanerday/open-ai',
          ':link2' => 'https://github.com/openai-php/client',
        ]),
      '#default_value' => $this->configuration['sdk'] ?? 'orhanerday',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['sdk'] = $form_state->getValue('sdk');
  }

  /**
   * Gets the OpenAI SDK API client.
   *
   * @return mixed
   *   The OpenAI SDK.
   */
  public function getClient(): mixed {
    if ($this->getSdk() === 'orhanerday') {
      return $this->getOrhanerdayClient();
    }

    return $this->getOpenAiClient();
  }

  /**
   * Gets an OpenAI client using the "orhanerday/open-ai" SDK.
   *
   * @return \Orhanerday\OpenAi\OpenAi
   *   The "orhanerday/open-ai" OpenAI SDK.
   */
  private function getOrhanerdayClient(): OrhanerdayOpenAi {

    // Only if not initialized yet.
    if (empty($this->client)) {
      $api_key = $this->getKeyValue();

      // Initialize API client.
      $this->client = new OrhanerdayOpenAi($api_key);
    }
    return $this->client;
  }

  /**
   * Gets an OpenAI client using the "openai-php/client" SDK.
   *
   * @return \OpenAI\Client
   *   The "openai-php/client" OpenAI SDK.
   */
  private function getOpenAiClient(): Client {

    // Only if not initialized yet.
    if (empty($this->client)) {
      $api_key = $this->getKeyValue();

      return \OpenAI::client($api_key);
    }
    return $this->client;
  }

  /**
   * Gets the name of the selected SDK to use.
   *
   * @return string
   *   The name of the SDK to use.
   */
  protected function getSdk() {
    $sdk = $this->configuration['sdk'] ?? 'orhanerday';
    return $sdk;
  }

  /**
   * Breaks very large strings into chunks and reading them one-at-a-time.
   *
   * @param string $input
   *   The large string to process.
   * @param int $min
   *   The minimum length of the processed input line.
   * @param int $max
   *   The maximum length of the processed input line.
   *
   * @return array
   *   An array containing the string chunks.
   */
  public function stringToChunks(string $input, int $min = 4000, int $max = 6000) {
    $chunks = [];
    $lines = explode("\n", $input);
    $chunk_txt = '';
    $chunk_len = 0;
    $line_count = count($lines);
    $i = -1;
    $prev_line_was_heading = FALSE;

    foreach ($lines as $this_line) {
      $i++;
      $this_line = trim($this_line);

      // Ignore blank lines.
      if (empty($this_line)) {
        continue;
      }

      // Is the next line blank?
      $next = FALSE;

      if ($i < $line_count - 1) {
        $next = !!trim($lines[$i + 1]);
      }

      // Is the previous line blank?
      $prev = FALSE;

      if ($i > 0) {
        $prev = !!trim($lines[$i - 1]);
      }

      // Headings.
      // A heading should force the purge of the previous chunk,.
      // Unless the previous line was also a heading.
      if ($this->headingLike($this_line) &&!$prev_line_was_heading && !$prev && !$next) {
        $prev_line_was_heading = TRUE;

        if ($chunk_txt) {
          // Content exists already - make a chunk for it.
          $chunks[] = $chunk_txt;
          $chunk_txt = '';
          $chunk_len = 0;
        }

        // Add the line break in for the heading.
        $this_line = "$this_line\n";
      }
      else {
        // Not a heading.
        $prev_line_was_heading = FALSE;
      }

      $this_line_len = strlen($this_line);
      $new_len = $this_line_len + $chunk_len;

      // If this is a new chunk and the line is long enough, chunk and continue.
      if ($new_len > $min and $this_line_len < $max) {
        $chunks[] = $this_line;
        $chunk_txt = '';
        $chunk_len = 0;
        continue;
      }

      // If this line can squeeze in under the max, add chunk and continue.
      if ($new_len > $min && $new_len < $max) {
        // New line can squeeze in.
        $chunk_txt = "\n$this_line";
        $chunks[] = $chunk_txt;
        $chunk_txt = '';
        $chunk_len = 0;
        continue;
      }

      // If the line safely fits under the max, continue.
      if ($new_len <= $max) {
        $chunk_txt .= "\n$this_line";
        continue;
      }
      else {
        // We are left with a line that is too long for the chunk.
        // Clear out the last chunk, even if it is small.
        $chunks[] = $chunk_txt;
        $chunk_txt = '';
        $chunk_len = 0;
      }

      // Starting fresh.
      if ($this_line_len < $max) {
        $chunk_txt .= "\n$this_line";
        continue;
      }

      else {
        $chunk_txt = '';

        // The line is too long! Start working with sentences.
        // Split into parts and sentences with delimiter.
        $sentences = [];
        $parts = preg_split('/([.?!:])/', $this_line, -1, PREG_SPLIT_DELIM_CAPTURE);

        for ($si = 0, $n = count($parts) - 1; $si <= $n; $si += 2) {
          $sentences[] = $parts[$si] . ($parts[$si + 1] ?? '');
        }

        foreach ($sentences as $sentence) {
          $sentence = trim($sentence);

          if (strlen($sentence) > 0) {
            $chunk_txt .= "$sentence ";

            if (strlen($chunk_txt) > $min) {
              $chunks[] = $chunk_txt;
              $chunk_txt = '';
              $chunk_len = 0;
            }
            else {
              $chunk_txt .= $sentence;
            }
          }
        }

        // Mop up any left over sentences.
        if (strlen($chunk_txt) > 0) {
          $chunks[] = $chunk_txt;
          $chunk_txt = '';
          $chunk_len = 0;
          continue;
        }
      }
    }

    // Tidy up remaining chunk.
    $chunks[] = $chunk_txt;

    return $chunks;
  }

  /**
   * Helper function to determine if a given line look like a heading.
   *
   * @param string $line
   *   The line to be processed.
   *
   * @return bool
   *   TRUE if the line looks like a heading, FALSE otherwise.
   */
  private function headingLike(string $line) {
    if (!in_array(substr($line, -1), ['.', '!', '?']) && strlen($line) < 255) {
      return TRUE;
    }

    return FALSE;
  }

}
