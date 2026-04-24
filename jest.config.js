module.exports = {
  testEnvironment: 'node',

  modulePathIgnorePatterns: [
    '<rootDir>/.claude/',
    '<rootDir>/.cursor/',
    '<rootDir>/.wp-env/',
    '<rootDir>/worktrees/',
    '<rootDir>/tests/e2e/',
  ],

  testPathIgnorePatterns: [
    '/node_modules/',
    '/.claude/',
    '/.cursor/',
    '/.wp-env/',
    '/worktrees/',
    '/tests/e2e/',
  ],

  haste: {
    enableSymlinks: false,
    throwOnModuleCollision: false,
  },
};
