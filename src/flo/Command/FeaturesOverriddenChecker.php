<?php

/**
 * Checks to see if any features have been overridden.
 */

namespace flo\Command;

use flo\Drupal;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Github;


class FeaturesOverriddenChecker extends Command {
  protected function configure() {
    $this->setName('check-features')
      ->setDescription('Runs `drush features-list` to check features overridden status.');
  }

  /**
   * Process the check-features command.
   *
   * {@inheritDoc}
   *
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $gh_status_post = FALSE;
    $targetBranch = getenv(self::GITHUB_PULL_REQUEST_TARGET_BRANCH);
    $targetRef = getenv(self::GITHUB_PULL_REQUEST_COMMIT);
    $targetURL = getenv(self::JENKINS_BUILD_URL);
    $pullRequest = getenv(self::GITHUB_PULL_REQUEST_ID);
    $github = $this->getGithub();

    // Check if we're going to post to GH or not.
    if (!empty($targetRef) && !empty($targetURL)) {
      // Set the $gh_status_post variable to TRUE if we can post to GH.
      $gh_status_post = TRUE;
    }

    $git_root = new Process('git rev-parse --show-toplevel');
    $git_root->run();
    if (!$git_root->isSuccessful()) {
      throw new \RuntimeException($git_root->getErrorOutput());
    }

    $current_dir = new Process('pwd');
    $current_dir->run();
    if (!$current_dir->isSuccessful()) {
      throw new \RuntimeException($current_dir->getErrorOutput());
    }

    if ($git_root->getOutput() !== $current_dir->getOutput()) {
      throw new \Exception("You must run check-features from the git root.");
    }

    $pull_request = $this->getConfigParameter('pull_request');
    $path = "{$pull_request['prefix']}-{$pullRequest}.{$pull_request['domain']}";
    $pr_directories = $this->getConfigParameter('pr_directories');

    $process = new Process("cd {$pr_directories}{$path}/docroot && drush features-list");
    $process->setTimeout(60 * 2);
    $process->run();

    $overridden = preg_grep('Overridden' ,$process->getOutput());

    if ($overridden) {
      $output->writeln("<error>There are some overridden features.</error>");
      $output->writeln($process->getOutput());
      $gh_status_state = 'failure';
      $gh_status_desc = 'Drush: Feature Checker failure.';
    }
    else {
      $output->writeln("<info>No overridden features have been found.</info>");
      $gh_status_state = 'success';
      $gh_status_desc = 'Drush: Feature Checker success.';
      $output->writeln("<info>PR #$pullRequest has been checked successfully.</info>");
    }

    $output->writeln($this->getConfigParameter('organization'));
    $output->writeln($this->getConfigParameter('repository'));
    $output->writeln($targetRef);
    $output->writeln($targetURL);
    $output->writeln($pullRequest);
    $output->writeln($pr_directories . $path);

    // Post to GH if we're allowed.
    if ($gh_status_post) {
      $github->api('repo')->statuses()->create(
        $this->getConfigParameter('organization'),
        $this->getConfigParameter('repository'),
        $targetRef,
        array(
          'state' => $gh_status_state,
          'target_url' => $targetURL,
          'description' => $gh_status_desc,
          'context' => "drush/features-checker",
        )
      );
    }
  }
}
