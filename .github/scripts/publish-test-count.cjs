const { readFileSync } = require('node:fs');

module.exports = async ({ github, context, core }) => {
  const repo = context.repo;
  const git = github.rest.git;
  const { data: master } = await git.getRef({ ...repo, ref: 'heads/master' });
  if (master.object.sha !== context.sha) {
    core.info('Пропускаем публикацию отчёта устаревшего коммита.');
    return;
  }

  const content = readFileSync(process.env.BADGE_PATH, 'utf8');
  JSON.parse(content);
  let previous;
  try {
    const { data } = await git.getRef({ ...repo, ref: 'heads/badges' });
    previous = data.object.sha;
  } catch (error) {
    if (error.status !== 404) throw error;
  }

  // Отдельная ветка содержит только данные бейджей и не меняет master.
  let baseTree;
  if (previous) {
    const { data } = await git.getCommit({ ...repo, commit_sha: previous });
    baseTree = data.tree.sha;
  }
  const { data: tree } = await git.createTree({
    ...repo,
    ...(baseTree ? { base_tree: baseTree } : {}),
    tree: [{ path: 'test-count.json', mode: '100644', type: 'blob', content }],
  });
  if (tree.sha === baseTree) return;

  const { data: commit } = await git.createCommit({
    ...repo,
    message: `Update test count for ${context.sha}`,
    tree: tree.sha,
    parents: previous ? [previous] : [],
  });
  if (previous) {
    await git.updateRef({ ...repo, ref: 'heads/badges', sha: commit.sha, force: false });
  } else {
    await git.createRef({ ...repo, ref: 'refs/heads/badges', sha: commit.sha });
  }
};
