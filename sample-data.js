/* Placeholder content so the Study Hub can be demoed without a database.
   When the database is connected, replace these with data loaded from the server.
   Topic: photosynthesis. Each question's `topic` must match a note heading below,
   because the quiz result groups wrong answers by topic.
   Note: keep digits out of `q` and `explain` text. sampleQuestions() in studyhub.js renumbers the first
   number it finds when it repeats these questions to build a bigger quiz. */

/* SAMPLE CONTENT */
const SAMPLE_NOTES = [{
        h: 'What is photosynthesis?',
        p: 'Photosynthesis is how plants, algae and some bacteria turn light energy into chemical energy. Using sunlight, water and carbon dioxide, they make glucose and release oxygen. It takes place inside chloroplasts, which contain the green pigment chlorophyll.'
    },
    {
        h: 'Light-dependent reactions',
        p: 'These reactions happen in the thylakoid membranes. Chlorophyll absorbs light, which splits water molecules and releases oxygen. The energy that is captured is stored in ATP and NADPH.'
    },
    {
        h: 'The Calvin cycle',
        p: 'The Calvin cycle takes place in the stroma and does not need light directly. It uses the ATP and NADPH made in the light-dependent reactions to turn carbon dioxide into glucose.'
    },
    {
        h: 'Quick recap',
        p: 'The light-dependent reactions capture energy and release oxygen, and the Calvin cycle uses that energy to build sugar. Overall, carbon dioxide and water, with light energy, become glucose and oxygen.'
    }
];

const SAMPLE_QUESTIONS = [{
        q: 'Where in the plant cell does photosynthesis take place?',
        options: ['Mitochondria', 'Chloroplasts', 'Nucleus', 'Ribosomes'],
        answer: 1,
        topic: 'What is photosynthesis?',
        explain: 'Photosynthesis happens in the chloroplasts, which contain chlorophyll.'
    },
    {
        q: 'What is released when water is split in the light-dependent reactions?',
        options: ['Oxygen', 'Glucose', 'Carbon dioxide', 'Nitrogen'],
        answer: 0,
        topic: 'Light-dependent reactions',
        explain: 'Splitting water releases oxygen as a by-product.'
    },
    {
        q: 'Where does the Calvin cycle take place?',
        options: ['Thylakoid membrane', 'Cell wall', 'Stroma', 'Cytoplasm'],
        answer: 2,
        topic: 'The Calvin cycle',
        explain: 'The Calvin cycle happens in the stroma of the chloroplast.'
    },
    {
        q: 'Which gas do plants take in for photosynthesis?',
        options: ['Oxygen', 'Nitrogen', 'Hydrogen', 'Carbon dioxide'],
        answer: 3,
        topic: 'What is photosynthesis?',
        explain: 'Plants take in carbon dioxide and use it to build glucose.'
    },
    {
        q: 'Which molecules store the energy captured in the light-dependent reactions?',
        options: ['DNA and RNA', 'ATP and NADPH', 'Starch and cellulose', 'Lipids and proteins'],
        answer: 1,
        topic: 'Light-dependent reactions',
        explain: 'The energy is stored in ATP and NADPH, which the Calvin cycle then uses.'
    }
];

/* Text-answer questions: the student types the answer.
   `answer` is the main answer; `accept` is an optional list of other accepted wordings. */
const SAMPLE_TEXT_QUESTIONS = [{
        type: 'text',
        q: 'Type the name of the green pigment that absorbs light for photosynthesis.',
        answer: 'Chlorophyll',
        accept: ['chlorophyll a'],
        topic: 'What is photosynthesis?',
        explain: 'Chlorophyll absorbs light energy, mainly red and blue light.'
    },
    {
        type: 'text',
        q: 'Type the name of the membranes inside chloroplasts where the light-dependent reactions happen.',
        answer: 'Thylakoid',
        accept: ['thylakoids', 'thylakoid membrane', 'thylakoid membranes'],
        topic: 'Light-dependent reactions',
        explain: 'The light-dependent reactions take place in the thylakoid membranes.'
    },
    {
        type: 'text',
        q: 'Type the name of the cycle that turns carbon dioxide into glucose.',
        answer: 'Calvin cycle',
        accept: ['the calvin cycle', 'calvin', 'calvin benson cycle', 'calvin-benson cycle'],
        topic: 'The Calvin cycle',
        explain: 'The Calvin cycle uses ATP and NADPH to build glucose from carbon dioxide.'
    },
    {
        type: 'text',
        q: 'Type the sugar that is the main product of photosynthesis.',
        answer: 'Glucose',
        accept: ['c6h12o6'],
        topic: 'What is photosynthesis?',
        explain: 'Glucose is the sugar plants make and use as an energy source.'
    },
    {
        type: 'text',
        q: 'Type the gas that is released when water is split.',
        answer: 'Oxygen',
        accept: ['o2'],
        topic: 'Light-dependent reactions',
        explain: 'Oxygen is released as a by-product when water is split.'
    }
];