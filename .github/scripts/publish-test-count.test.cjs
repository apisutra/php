const assert = require('node:assert/strict');
const { mkdtempSync, writeFileSync, rmSync } = require('node:fs');
const { tmpdir } = require('node:os');
const { join } = require('node:path');
const { test } = require('node:test');
const publish = require('./publish-test-count.cjs');

async function scenario(options = {}) {
  const calls = [];
  const directory = mkdtempSync(join(tmpdir(), 'apisutra-badge-'));
  const oldPath = process.env.BADGE_PATH;
  process.env.BADGE_PATH = join(directory, 'test-count.json');
  writeFileSync(process.env.BADGE_PATH, '{"schemaVersion":1,"label":"tests","message":"2 passed"}');
  const git = {};
  for (const method of ['getRef', 'getCommit', 'createTree', 'createCommit', 'updateRef', 'createRef']) {
    git[method] = async (args) => {
      calls.push({ method, args });
      if (method === 'getRef') {
        if (args.ref === 'heads/master') return { data: { object: { sha: options.stale ? 'newer' : 'current' } } };
        if (options.denied) throw Object.assign(new Error('Forbidden'), { status: 403 });
        if (!options.existing) throw Object.assign(new Error('Not found'), { status: 404 });
        return { data: { object: { sha: 'previous' } } };
      }
      if (method === 'getCommit') return { data: { tree: { sha: 'old-tree' } } };
      return { data: { sha: method === 'createTree' ? (options.unchanged ? 'old-tree' : 'new-tree') : 'new-commit' } };
    };
  }
  try {
    await publish({ github: { rest: { git } }, context: { repo: { owner: 'owner', repo: 'repo' }, sha: 'current' }, core: { info() {} } });
    return calls;
  } finally {
    if (oldPath === undefined) delete process.env.BADGE_PATH;
    else process.env.BADGE_PATH = oldPath;
    rmSync(directory, { recursive: true });
  }
}

test('Первый отчёт создаёт отдельную ветку без истории master', async () => {
  const calls = await scenario();
  assert.deepEqual(calls.find(c => c.method === 'createCommit').args.parents, []);
  assert.equal(calls.find(c => c.method === 'createRef').args.ref, 'refs/heads/badges');
  assert.equal(calls.find(c => c.method === 'createTree').args.tree[0].path, 'test-count.json');
});

test('Обновление сохраняет существующее дерево и не использует force', async () => {
  const calls = await scenario({ existing: true });
  assert.equal(calls.find(c => c.method === 'createTree').args.base_tree, 'old-tree');
  assert.deepEqual(calls.find(c => c.method === 'createCommit').args.parents, ['previous']);
  assert.equal(calls.find(c => c.method === 'updateRef').args.force, false);
  assert.equal(calls.find(c => c.method === 'updateRef').args.ref, 'heads/badges');
});

test('Устаревший запуск ничего не публикует', async () => {
  assert.equal((await scenario({ stale: true })).length, 1);
});

test('Неизменившийся бейдж не создаёт новый коммит', async () => {
  assert.equal((await scenario({ existing: true, unchanged: true })).some(c => c.method === 'createCommit'), false);
});

test('Ошибка доступа не считается отсутствующей веткой', async () => {
  await assert.rejects(scenario({ denied: true }), { status: 403 });
});
