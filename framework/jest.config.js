module.exports = {
	testEnvironment: 'jsdom',
	roots: [ '<rootDir>/tests/js' ],
	testMatch: [ '**/*.test.js' ],
	moduleNameMapper: {
		'^@/(.*)$': '<rootDir>/assets/js/src/$1',
	},
	transform: {
		'^.+\\.js$': 'babel-jest',
	},
	collectCoverageFrom: [
		'assets/js/src/**/*.js',
		'!assets/js/src/index.js',
	],
};

