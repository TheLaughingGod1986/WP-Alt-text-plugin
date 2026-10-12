const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

function loadJobState() {
    const values = new Map();
    const window = {
        addEventListener: jest.fn(),
        localStorage: {
            getItem: jest.fn( key => values.get( key ) || null ),
            setItem: jest.fn( ( key, value ) => values.set( key, value ) ),
            removeItem: jest.fn( key => values.delete( key ) ),
        },
    };

    vm.runInNewContext(
        fs.readFileSync( path.join( __dirname, '../assets/js/admin/job-state.js' ), 'utf8' ),
        { window, Date, JSON, Math, parseInt, setTimeout, clearTimeout }
    );

    return { jobState: window.bbaiJobState, values };
}

describe( 'background generation job state', () => {
    test( 'preserves a mixed batch as a persisted partial result', () => {
        const { jobState, values } = loadJobState();

        jobState.start( 'Generating ALT text', 5, 'bulk' );
        jobState.complete( { successes: 3, failures: 2 } );

        expect( jobState.getState() ).toMatchObject( {
            running: false,
            progress: 5,
            successes: 3,
            failures: 2,
            status: 'partial',
        } );

        const persisted = JSON.parse( values.get( 'bbai_active_generation_state_v1' ) );
        expect( persisted ).toMatchObject( {
            successes: 3,
            failures: 2,
            status: 'partial',
        } );
    } );
} );
