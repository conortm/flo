<?php

namespace flo\Command;

use flo\SymfonyOverwrite\Filesystem;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Yaml\Yaml;


class ProjectSetup extends Command {

  const FLO_YAML_PATH = './flo.yml';

  protected function configure() {
    $this->setName('project-setup')
      ->setDescription('Initializes proper flo.yml for for Flo projects.');
  }

  /**
   * Executes the flo project-setup command.
   * @param InputInterface $input
   * @param OutputInterface $output
   * @throws \Exception
   *
   * @return null
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->checkGitRoot();

    $fs = new Filesystem();
    $helper = $this->getHelper('question');

    if ($fs->exists($this::FLO_YAML_PATH)) {
      throw new \Exception("flo.yml already exists.");
    }

    // Questions.
    $app_type_question = new ChoiceQuestion(
      'Please select the app type (defaults to drupal7):',
      array('drupal7', 'drupal8', 'nodejs'),
      0
    );
    $org_question = new Question('Please enter the name of the GitHub organization/username (defaults to NBCUOTS): ', 'NBCUOTS');
    $repo_question = new Question('Please enter the name of your github repository: ');
    $shortname_question = new Question('Please enter project short name: ');
    $github_question = new Question('Please enter the GitHub git url: ');
    $acquia_question = new Question('Please enter the Acquia git url: ');
    $pull_request_question = new Question('Please enter the pull-request domain: ');
    $pull_request_prefix_question = new Question('Please enter the pull-request prefix (ex: p7): ');

    // Prompts.
    $app_type = $helper->ask($input, $output, $app_type_question);
    $organization = $helper->ask($input, $output, $org_question);
    $repository = $helper->ask($input, $output, $repo_question);
    $shortname = $helper->ask($input, $output, $shortname_question);
    $github_git_url = $helper->ask($input, $output, $github_question);
    $acquia_git_url = $helper->ask($input, $output, $acquia_question);
    $pull_request_domain = $helper->ask($input, $output, $pull_request_question);
    $pull_request_prefix = $helper->ask($input, $output, $pull_request_prefix_question);

    // Build flo.yml file.
    $flo_yml = array(
      'app_type' => $app_type,
      'organization' => $organization,
      'repository' => $repository,
      'shortname' => $shortname,
      'github_git_uri' => $github_git_url,
      'acquia_git_uri' => $acquia_git_url,
      'pull_request' => array(
        'domain' => $pull_request_domain,
        'prefix' => $pull_request_prefix,
      ),
    );

    $fs->dumpFile($this::FLO_YAML_PATH, Yaml::dump($flo_yml, 2, 2));
    if ($fs->exists($this::FLO_YAML_PATH)) {
      $output->writeln("<info>flo.yml created.</info>");
    }
  }
}
